<?php

namespace App\Services;

use App\Models\Indicator;
use App\Models\Municipality;
use App\Models\MunicipalityIndicatorValue;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class EconomicIndicatorSyncService
{
    private const AGGREGATES = [
        37 => ['code' => 'municipal_gdp', 'name' => 'Produto Interno Bruto a preços correntes', 'last_year' => 2023],
        513 => ['code' => 'agriculture_gva', 'name' => 'Valor adicionado bruto a preços correntes da agropecuária', 'last_year' => 2021],
        517 => ['code' => 'industry_gva', 'name' => 'Valor adicionado bruto a preços correntes da indústria', 'last_year' => 2021],
        6575 => ['code' => 'services_gva', 'name' => 'Valor adicionado bruto a preços correntes dos serviços, exclusive administração, defesa, educação e saúde públicas e seguridade social', 'last_year' => 2021],
        525 => ['code' => 'public_administration_gva', 'name' => 'Valor adicionado bruto a preços correntes da administração, defesa, educação e saúde públicas e seguridade social', 'last_year' => 2021],
    ];

    private int $requests = 0;

    public function requestCount(): int
    {
        return $this->requests;
    }

    public function sync(?callable $progress = null): array
    {
        $this->requests = 0;
        $municipalities = Municipality::query()->pluck('id', 'ibge_code')->all();
        $prefixes = [];
        foreach ($municipalities as $code => $id) {
            $prefix = substr((string) $code, 0, 6);
            if (! preg_match('/^[0-9]{7}$/D', (string) $code) || isset($prefixes[$prefix])) {
                throw new RuntimeException("Código municipal inválido ou prefixo ambíguo: {$code}.");
            }
            $prefixes[$prefix] = $id;
        }
        if ($municipalities === []) {
            throw new RuntimeException('Cadastre os municípios antes de sincronizar a economia.');
        }

        $metadata = $this->get('https://servicodados.ibge.gov.br/api/v1/pesquisas/38/indicadores/47001');
        $research = $this->singleIndicator($metadata, 47001);
        if (data_get($research, 'indicador') !== 'Série revisada'
            || data_get($research, 'unidade.id') !== 'R$' || data_get($research, 'unidade.multiplicador') !== 1) {
            throw new RuntimeException('Metadados inesperados para Pesquisa 38 / indicador 47001.');
        }

        $counts = [];
        foreach (self::AGGREGATES as $variable => $definition) {
            $indicator = $this->definition($definition['code'], $definition['name'], 'Mil Reais', 'ibge_aggregates');
            $indicator['source_aggregate_id'] = 5938;
            $indicator['source_variable_id'] = $variable;
            $indicator['source_methodological_reference'] = 'Referência 2010';
            foreach (range(2002, $definition['last_year']) as $year) {
                try {
                    $payload = $this->get("https://servicodados.ibge.gov.br/api/v3/agregados/5938/periodos/{$year}/variaveis/{$variable}", ['localidades' => 'N6[all]']);
                    $item = $this->singleIndicator($payload, $variable);
                    if (data_get($item, 'unidade') !== 'Mil Reais' || data_get($item, 'variavel') !== $definition['name']
                        || ! is_array(data_get($item, 'resultados')) || ! array_is_list($item['resultados'])
                        || count($item['resultados']) !== 1 || data_get($item, 'resultados.0.classificacoes') !== []) {
                        throw new RuntimeException('Unidade, nome ou classificação inesperada no agregado 5938.');
                    }
                    $rows = $this->mapRows(data_get($item, 'resultados.0.series'), $year, $municipalities, false);
                    $counts[$indicator['code']][$year] = $this->persist($indicator, $year, $rows, count($municipalities));
                } catch (Throwable $exception) {
                    throw new RuntimeException("{$indicator['code']}/{$year}: {$exception->getMessage()}", 0, $exception);
                }
                $progress && $progress($indicator['code'], $year, $counts[$indicator['code']][$year]);
            }
        }

        $indicator = $this->definition('municipal_gdp_per_capita', 'PIB per capita / Série revisada', 'R$', 'ibge_research');
        $indicator['source_research_id'] = 38;
        $indicator['source_indicator_id'] = 47001;
        $indicator['source_series'] = 'Série revisada';
        foreach (range(2010, 2023) as $year) {
            try {
                $payload = $this->get("https://servicodados.ibge.gov.br/api/v1/pesquisas/38/periodos/{$year}/indicadores/47001/resultados/1xxxxx|2xxxxx|3xxxxx|4xxxxx|5xxxxx");
                $item = $this->singleIndicator($payload, 47001);
                $rows = $this->mapRows(data_get($item, 'res'), $year, $prefixes, true);
                $counts[$indicator['code']][$year] = $this->persist($indicator, $year, $rows, count($municipalities));
            } catch (Throwable $exception) {
                throw new RuntimeException("{$indicator['code']}/{$year}: {$exception->getMessage()}", 0, $exception);
            }
            $progress && $progress($indicator['code'], $year, $counts[$indicator['code']][$year]);
        }

        return ['counts' => $counts, 'requests' => $this->requests];
    }

    private function get(string $url, array $query = []): mixed
    {
        return Http::acceptJson()->connectTimeout(10)->timeout(90)
            ->beforeSending(function (): void {
                $this->requests++;
            })
            ->retry(3, 500, fn (Throwable $exception): bool => $exception instanceof ConnectionException
                || ($exception instanceof RequestException && ($exception->response->status() === 429 || $exception->response->serverError())))
            ->get($url, $query)->throw()->json();
    }

    private function singleIndicator(mixed $payload, int $id): array
    {
        if (! is_array($payload) || ! array_is_list($payload) || count($payload) !== 1
            || ! is_array($payload[0]) || ! in_array(data_get($payload[0], 'id'), [$id, (string) $id], true)) {
            throw new RuntimeException("Resposta inválida ou indicador diferente de {$id}.");
        }

        return $payload[0];
    }

    private function mapRows(mixed $series, int $year, array $localities, bool $research): array
    {
        if (! is_array($series) || ! array_is_list($series) || $series === []) {
            throw new RuntimeException('Lista de resultados ausente ou vazia.');
        }
        $rows = [];
        foreach ($series as $item) {
            $code = data_get($item, $research ? 'localidade' : 'localidade.id');
            $values = data_get($item, $research ? 'res' : 'serie');
            $pattern = $research ? '/^[0-9]{6}$/D' : '/^[0-9]{7}$/D';
            if (! is_string($code) || ! preg_match($pattern, $code) || ! isset($localities[$code])
                || array_key_exists($localities[$code], $rows)
                || (! $research && data_get($item, 'localidade.nivel.id') !== 'N6')
                || ! is_array($values) || array_keys($values) !== [$year]) {
                throw new RuntimeException('Código municipal desconhecido/duplicado, nível ou período inválido.');
            }
            $value = $values[$year];
            if (in_array($value, [null, '...', '..', 'X'], true)) {
                $rows[$localities[$code]] = null;
            } elseif ($value === '-') {
                $rows[$localities[$code]] = '0';
            } elseif (is_string($value) && preg_match('/^-?[0-9]{1,18}(?:\.[0-9]{1,6})?$/D', $value)) {
                $rows[$localities[$code]] = $value;
            } else {
                throw new RuntimeException("Valor inválido ou fora da precisão decimal(24,6), município {$code}.");
            }
        }

        return $rows;
    }

    private function definition(string $code, string $name, string $unit, string $type): array
    {
        return [
            'code' => $code, 'name' => $name, 'unit' => $unit, 'source' => 'IBGE - Produto Interno Bruto dos Municípios',
            'source_type' => $type, 'source_aggregate_id' => null, 'source_variable_id' => null,
            'source_research_id' => null, 'source_indicator_id' => null,
            'source_methodological_reference' => null, 'source_series' => null,
        ];
    }

    private function persist(array $definition, int $year, array $rows, int $registered): array
    {
        $available = array_filter($rows, fn ($value): bool => $value !== null);
        $unavailable = array_keys(array_filter($rows, fn ($value): bool => $value === null));
        DB::transaction(function () use ($definition, $year, $available, $unavailable): void {
            Indicator::upsert([$definition], ['code'], array_diff(array_keys($definition), ['code']));
            $indicatorId = Indicator::where('code', $definition['code'])->value('id');
            $values = [];
            foreach ($available as $municipalityId => $value) {
                $values[] = ['municipality_id' => $municipalityId, 'indicator_id' => $indicatorId, 'reference_year' => $year, 'value' => $value];
            }
            foreach (array_chunk($values, 500) as $chunk) {
                MunicipalityIndicatorValue::upsert($chunk, ['municipality_id', 'indicator_id', 'reference_year'], ['value']);
            }
            foreach (array_chunk($unavailable, 500) as $chunk) {
                MunicipalityIndicatorValue::where('indicator_id', $indicatorId)->where('reference_year', $year)->whereIn('municipality_id', $chunk)->delete();
            }
        });

        return ['values' => count($available), 'unavailable' => count($unavailable), 'absent' => $registered - count($rows)];
    }
}
