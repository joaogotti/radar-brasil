<?php

namespace App\Services;

use App\Models\Indicator;
use App\Models\Municipality;
use App\Models\MunicipalityIndicatorValue;
use App\Models\State;
use App\Models\StateIndicatorValue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class CensusIndicatorSyncService
{
    private const YEAR = 2022;

    private const INDICATORS = [
        93 => ['code' => 'population_census', 'name' => 'População residente no Censo', 'unit' => 'Pessoas'],
        6318 => ['code' => 'territorial_area', 'name' => 'Área da unidade territorial', 'unit' => 'Quilômetros quadrados'],
        614 => ['code' => 'population_density', 'name' => 'Densidade demográfica no Censo', 'unit' => 'Habitante por quilômetro quadrado'],
    ];

    public function sync(): array
    {
        $payload = Http::acceptJson()->connectTimeout(10)->timeout(90)->retry(3, 500)
            ->get('https://servicodados.ibge.gov.br/api/v3/agregados/4714/periodos/2022/variaveis/93|6318|614', [
                'localidades' => 'N6[all]|N3[all]',
            ])->throw()->json();

        $localities = [
            'N6' => Municipality::query()->pluck('id', 'ibge_code')->all(),
            'N3' => State::query()->pluck('id', 'ibge_code')->all(),
        ];
        $rows = $this->validateAndMap($payload, $localities);

        DB::transaction(function () use ($rows): void {
            $definitions = [];
            foreach (self::INDICATORS as $variable => $indicator) {
                $definitions[] = [...$indicator, 'source' => 'IBGE - Censo Demográfico', 'source_aggregate_id' => 4714, 'source_variable_id' => $variable];
            }
            Indicator::upsert($definitions, ['code'], ['name', 'unit', 'source', 'source_aggregate_id', 'source_variable_id']);
            $ids = Indicator::query()->pluck('id', 'code');

            foreach (self::INDICATORS as $variable => $indicator) {
                foreach (['N6', 'N3'] as $level) {
                    $values = array_map(fn (array $row): array => [
                        ...$row, 'indicator_id' => $ids[$indicator['code']], 'reference_year' => self::YEAR,
                    ], $rows[$variable][$level]);

                    foreach (array_chunk($values, 500) as $chunk) {
                        if ($level === 'N6') {
                            MunicipalityIndicatorValue::upsert($chunk, ['municipality_id', 'indicator_id', 'reference_year'], ['value']);
                        } else {
                            StateIndicatorValue::upsert($chunk, ['state_id', 'indicator_id', 'reference_year'], ['value']);
                        }
                    }
                }
            }
        });

        $counts = [];
        foreach (self::INDICATORS as $variable => $indicator) {
            $counts[$indicator['code']] = ['municipalities' => count($rows[$variable]['N6']), 'states' => count($rows[$variable]['N3'])];
        }

        return [
            'counts' => $counts,
            'missing_municipalities' => array_values(array_diff(array_keys($localities['N6']), array_keys($rows[93]['N6']))),
            'missing_states' => array_values(array_diff(array_keys($localities['N3']), array_keys($rows[93]['N3']))),
        ];
    }

    private function validateAndMap(mixed $payload, array $localities): array
    {
        if (! is_array($payload) || ! array_is_list($payload) || count($payload) !== count(self::INDICATORS)) {
            throw new RuntimeException('Resposta do IBGE não contém os três indicadores esperados.');
        }

        $rows = [];
        foreach ($payload as $variable) {
            $id = data_get($variable, 'id');
            if (! is_scalar($id) || ! isset(self::INDICATORS[$id]) || isset($rows[$id])
                || data_get($variable, 'unidade') !== self::INDICATORS[$id]['unit']
                || ! is_array(data_get($variable, 'resultados')) || count($variable['resultados']) !== 1
                || data_get($variable, 'resultados.0.classificacoes') !== []) {
                throw new RuntimeException('Variável, unidade ou classificação inesperada na resposta do IBGE.');
            }

            $series = data_get($variable, 'resultados.0.series');
            if (! is_array($series) || ! array_is_list($series) || $series === []) {
                throw new RuntimeException('Séries ausentes na resposta do IBGE.');
            }

            $rows[$id] = ['N6' => [], 'N3' => []];
            foreach ($series as $seriesItem) {
                $code = data_get($seriesItem, 'localidade.id');
                $level = data_get($seriesItem, 'localidade.nivel.id');
                $values = data_get($seriesItem, 'serie');
                if (! in_array($level, ['N6', 'N3'], true) || ! is_scalar($code)
                    || ! isset($localities[$level][$code]) || isset($rows[$id][$level][$code])
                    || ! is_array($values) || array_keys($values) !== [self::YEAR]) {
                    throw new RuntimeException('Localidade desconhecida/duplicada ou período inesperado na resposta do IBGE.');
                }

                $value = $values[self::YEAR];
                if (! is_string($value) || ! preg_match('/^\d{1,18}(?:\.\d{1,6})?$/D', $value)
                    || ((int) $id === 93 && ! ctype_digit($value))) {
                    throw new RuntimeException("Valor indisponível ou fora da precisão suportada: variável {$id}, localidade {$code}.");
                }

                $foreignKey = $level === 'N6' ? 'municipality_id' : 'state_id';
                $rows[$id][$level][$code] = [$foreignKey => $localities[$level][$code], 'value' => $value];
            }

            foreach (['N6', 'N3'] as $level) {
                ksort($rows[$id][$level]);
                if ($rows[$id][$level] === []) {
                    throw new RuntimeException('Cobertura territorial incompleta na resposta do IBGE.');
                }
            }
        }

        foreach ([6318, 614] as $id) {
            foreach (['N6', 'N3'] as $level) {
                if (array_keys($rows[$id][$level]) !== array_keys($rows[93][$level])) {
                    throw new RuntimeException('Cobertura territorial divergente entre os indicadores.');
                }
            }
        }

        return $rows;
    }
}
