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

class CempreIndicatorSyncService
{
    private const YEARS = [2022, 2023, 2024];

    private const INDICATORS = [
        706 => ['code' => 'cempre_local_units', 'name' => 'Unidades locais', 'official' => 'Número de unidades locais', 'unit' => 'Unidades', 'unit_id' => '1020',
            'definition' => 'Locais de atuação das organizações abrangidas pelo CEMPRE; não são entidades jurídicas distintas.'],
        367 => ['code' => 'cempre_active_organizations', 'name' => 'Empresas e organizações atuantes', 'official' => 'Número de empresas e outras organizações atuantes', 'unit' => 'Unidades', 'unit_id' => '1020',
            'definition' => 'Empresas e outras organizações com atuação no município; inclui organizações além de empresas e não representa apenas sedes.'],
        707 => ['code' => 'cempre_employed_personnel', 'name' => 'Pessoal ocupado total', 'official' => 'Pessoal ocupado total', 'unit' => 'Pessoas', 'unit_id' => '45',
            'definition' => 'Inclui pessoal assalariado e sócios/proprietários em atividade, conforme a definição do CEMPRE.'],
        708 => ['code' => 'cempre_salaried_personnel', 'name' => 'Pessoal ocupado assalariado', 'official' => 'Pessoal ocupado assalariado', 'unit' => 'Pessoas', 'unit_id' => '45',
            'definition' => 'Pessoal ocupado assalariado segundo o CEMPRE; não equivale automaticamente a empregados CLT.'],
        10143 => ['code' => 'cempre_average_monthly_salary', 'name' => 'Salário médio mensal', 'official' => 'Salário médio mensal em reais', 'unit' => 'Reais', 'unit_id' => '38',
            'definition' => 'Média publicada pelo CEMPRE para o universo assalariado. Não representa renda média dos moradores. Base de preços não esclarecida: sem classificação nominal/real, deflação, recálculo ou crescimento salarial.'],
    ];

    private const HEADER = [
        'NC' => 'Nível Territorial (Código)', 'NN' => 'Nível Territorial',
        'MC' => 'Unidade de Medida (Código)', 'MN' => 'Unidade de Medida', 'V' => 'Valor',
        'D1C' => 'Município (Código)', 'D1N' => 'Município',
        'D2C' => 'Variável (Código)', 'D2N' => 'Variável', 'D3C' => 'Ano (Código)', 'D3N' => 'Ano',
    ];

    private int $requests = 0;

    private int $calls = 0;

    public function sync(?callable $progress = null): array
    {
        $lock = fopen(storage_path('app/cempre.lock'), 'c');
        if ($lock === false) {
            throw new RuntimeException('Cannot open CEMPRE synchronization lock.');
        }
        try {
            if (! flock($lock, LOCK_EX | LOCK_NB)) {
                throw new RuntimeException('A CEMPRE synchronization is already running.');
            }

            return $this->synchronize($progress);
        } finally {
            fclose($lock);
        }
    }

    private function synchronize(?callable $progress): array
    {
        $started = microtime(true);
        $this->requests = $this->calls = 0;
        $municipalities = Municipality::pluck('id', 'ibge_code')->all();
        if ($municipalities === []) {
            throw new RuntimeException('Register municipalities before synchronizing CEMPRE.');
        }
        foreach ($municipalities as $code => $id) {
            if (! preg_match('/^[0-9]{7}$/D', (string) $code)) {
                throw new RuntimeException('Invalid registered municipality code.');
            }
        }

        $data = [];
        $confirmations = [];
        foreach (self::YEARS as $year) {
            $progress && $progress("CEMPRE {$year}: consulta nacional.");
            $data[$year] = $this->read('all', [$year], $municipalities)[$year];
            $missing = array_diff_key($municipalities, $data[$year][706]);
            foreach (array_chunk(array_keys($missing), 100) as $codes) {
                $confirmations[implode(',', $codes)][] = $year;
            }
        }
        foreach ($confirmations as $codes => $years) {
            $progress && $progress('CEMPRE: confirmação explícita dos municípios ausentes, anos '.implode(',', $years).'.');
            $expected = array_intersect_key($municipalities, array_flip(explode(',', (string) $codes)));
            $confirmed = $this->read((string) $codes, $years, $expected);
            foreach ($years as $year) {
                foreach (self::INDICATORS as $variable => $definition) {
                    if (count($confirmed[$year][$variable]) !== count($expected)) {
                        throw new RuntimeException('Unresolved CEMPRE municipality omission.');
                    }
                    $data[$year][$variable] += $confirmed[$year][$variable];
                }
            }
        }
        $counts = [];
        foreach ($data as $year => $variables) {
            foreach ($variables as $variable => $rows) {
                if (count($rows) !== count($municipalities)) {
                    throw new RuntimeException('Incomplete CEMPRE coverage.');
                }
                $counts[$year][self::INDICATORS[$variable]['code']] = array_fill_keys(['available', 'unavailable', 'suppressed', 'not_applicable'], 0);
                foreach ($rows as $raw) {
                    $counts[$year][self::INDICATORS[$variable]['code']][$this->parse($raw)['status']]++;
                }
            }
        }

        $progress && $progress('CEMPRE: série validada; publicação atômica.');
        DB::transaction(function () use ($data, $municipalities): void {
            $now = now();
            foreach (self::INDICATORS as $variable => $definition) {
                $catalog = $this->catalog($variable, $definition);
                Indicator::upsert([$catalog], ['code'], array_diff(array_keys($catalog), ['code']));
                $id = Indicator::where('code', $definition['code'])->value('id');
                $batch = [];
                foreach ($data as $year => $variables) {
                    foreach ($variables[$variable] as $code => $raw) {
                        $batch[] = [...$this->parse($raw), 'municipality_id' => $municipalities[$code], 'indicator_id' => $id,
                            'reference_year' => $year, 'last_synced_at' => $now];
                        if (count($batch) === 500) {
                            $this->write($batch);
                            $batch = [];
                        }
                    }
                }
                if ($batch !== []) {
                    $this->write($batch);
                }
            }
        });

        return ['counts' => $counts, 'total' => count($municipalities) * count(self::INDICATORS) * count(self::YEARS),
            'requests' => $this->requests, 'retries' => $this->requests - $this->calls,
            'seconds' => round(microtime(true) - $started, 2), 'peak_memory_mb' => round(memory_get_peak_usage(true) / 1048576, 2)];
    }

    private function read(string $selection, array $years, array $municipalities): array
    {
        $this->calls++;
        $url = 'https://apisidra.ibge.gov.br/values/t/9509/n6/'.$selection.'/v/'.implode(',', array_keys(self::INDICATORS)).'/p/'.implode(',', $years);
        $payload = Http::acceptJson()->connectTimeout(10)->timeout(120)
            ->beforeSending(function (): void {
                $this->requests++;
            })
            ->retry(3, 500, fn (Throwable $exception): bool => $exception instanceof ConnectionException
                || ($exception instanceof RequestException && ($exception->response->status() === 429 || $exception->response->serverError())))
            ->get($url)->throw()->json();
        if (! is_array($payload) || ! array_is_list($payload) || count($payload) < 2 || $payload[0] != self::HEADER) {
            throw new RuntimeException('Invalid SIDRA structure or header.');
        }
        $rows = [];
        foreach ($payload as $index => $item) {
            if ($index === 0) {
                continue;
            }
            $code = $item['D1C'] ?? null;
            $variable = $item['D2C'] ?? null;
            $year = $item['D3C'] ?? null;
            if (! is_array($item) || count($item) !== count(self::HEADER)
                || ($item['NC'] ?? null) !== '6' || ($item['NN'] ?? null) !== 'Município'
                || ! is_string($code) || ! preg_match('/^[0-9]{7}$/D', $code) || ! isset($municipalities[$code])
                || ! is_string($variable) || ! isset(self::INDICATORS[$variable])
                || ! is_string($year) || ! in_array($year, array_map('strval', $years), true)
                || ($item['D3N'] ?? null) !== $year || ! is_string($item['D1N'] ?? null) || $item['D1N'] === ''
                || ($item['D2N'] ?? null) !== self::INDICATORS[$variable]['official']
                || ($item['MC'] ?? null) !== self::INDICATORS[$variable]['unit_id']
                || ($item['MN'] ?? null) !== self::INDICATORS[$variable]['unit']
                || isset($rows[$year][$variable][$code])) {
                throw new RuntimeException('Invalid CEMPRE municipality, variable, year, unit or duplicate observation.');
            }
            $raw = $item['V'] ?? null;
            $this->parse($raw);
            $rows[$year][$variable][$code] = $raw;
        }
        foreach ($years as $year) {
            if (count($rows[$year] ?? []) !== count(self::INDICATORS)) {
                throw new RuntimeException('Missing CEMPRE year or variable.');
            }
            foreach (self::INDICATORS as $variable => $definition) {
                if (count($rows[$year][$variable]) !== count($rows[$year][706])
                    || array_diff_key($rows[$year][706], $rows[$year][$variable]) !== []) {
                    throw new RuntimeException('CEMPRE variables have different municipality coverage.');
                }
            }
        }

        return $rows;
    }

    private function parse(mixed $raw): array
    {
        $status = match ($raw) {
            '...' => 'unavailable', '..' => 'not_applicable', 'X' => 'suppressed', default => 'available',
        };
        $value = $raw === '-' ? '0' : $raw;
        if ($status === 'available' && (! is_string($value) || ! preg_match('/^-?[0-9]{1,18}(?:\.[0-9]{1,6})?$/D', $value))) {
            throw new RuntimeException('Invalid CEMPRE value or unsupported decimal(24,6) precision.');
        }

        return ['value' => $status === 'available' ? $value : null, 'status' => $status, 'source_value' => $raw];
    }

    private function catalog(int $variable, array $definition): array
    {
        return [
            'code' => $definition['code'], 'name' => $definition['name'], 'unit' => $definition['unit'],
            'source' => 'IBGE — Estatísticas do Cadastro Central de Empresas — CEMPRE', 'source_type' => 'ibge_aggregates',
            'source_aggregate_id' => 9509, 'source_variable_id' => $variable,
            'source_research_id' => null, 'source_indicator_id' => null,
            'source_series' => 'CEMPRE — série iniciada em 2022 (V1: 2022–2024)',
            'source_methodological_reference' => 'Nota técnica 01/2024: ruptura em 2022; não concatenar com a tabela 1685. https://biblioteca.ibge.gov.br/visualizacao/livros/liv102096.pdf',
            'source_filters' => json_encode(['territorial_level' => 'N6', 'classifications' => []], JSON_THROW_ON_ERROR),
            'source_universe' => 'Organizações formais ativas e suas unidades locais no município, conforme o CEMPRE; exclui MEIs. Não representa o conjunto dos moradores ou a economia informal.',
            'source_definition' => $definition['official'].'. '.$definition['definition'].' Fonte: https://sidra.ibge.gov.br/tabela/9509',
        ];
    }

    private function write(array $batch): void
    {
        MunicipalityIndicatorValue::upsert($batch, ['municipality_id', 'indicator_id', 'reference_year'], ['value', 'status', 'source_value', 'last_synced_at']);
    }
}
