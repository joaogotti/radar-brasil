<?php

namespace App\Services;

use App\Models\Indicator;
use App\Models\Municipality;
use App\Models\MunicipalityIndicatorValue;
use App\Support\CensusSyncLock;
use App\Support\IbgeCensusValueParser;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class DemographicIndicatorSyncService
{
    public function sync(): array
    {
        return app(CensusSyncLock::class)->run('indicators', fn () => $this->synchronize());
    }

    private function synchronize(): array
    {
        $started = microtime(true);
        $reader = new IbgeCensusAggregateReader;
        $parser = new IbgeCensusValueParser;
        $municipalities = Municipality::pluck('id', 'ibge_code')->all();
        if ($municipalities === []) {
            throw new RuntimeException('Register municipalities before synchronizing.');
        }
        $year = config('demography.year');
        $groups = collect(config('demography.indicators'))->groupBy('aggregate', preserveKeys: true);
        $counts = [];
        foreach ($groups as $aggregate => $definitions) {
            $variables = $definitions->mapWithKeys(fn ($d) => [$d['variable'] => $d['unit']])->all();
            $payload = $reader->read($aggregate, $variables, $definitions->first()['filters'], $municipalities);
            $parsed = [];
            foreach ($definitions as $code => $definition) {
                $values = array_values($payload[$definition['variable']])[0]['values'];
                foreach ($values as $ibge => $raw) {
                    $parsed[$code][$ibge] = $parser->parse($raw, $definition['measure']);
                }
            }
            foreach ($definitions as $code => $definition) {
                $rows = $parsed[$code];
                $existing = MunicipalityIndicatorValue::whereHas('indicator', fn ($q) => $q->where('code', $code))->where('reference_year', $year)->where('status', 'available')->pluck('municipality_id')->all();
                foreach (array_diff_key($municipalities, $rows) as $ibge => $municipalityId) {
                    if (! in_array((int) $ibge, config('demography.known_omissions'), true) || in_array($municipalityId, $existing, true)) {
                        $confirmation = $reader->read($aggregate, [$definition['variable'] => $definition['unit']], $definition['filters'], $municipalities, [$ibge]);
                        $confirmed = array_values($confirmation[$definition['variable']])[0]['values'];
                        if (! array_key_exists($ibge, $confirmed)) {
                            throw new RuntimeException('Unresolved census omission: '.$ibge);
                        }
                        $rows[$ibge] = $parser->parse($confirmed[$ibge], $definition['measure']);
                    } else {
                        $rows[$ibge] = ['value' => null, 'status' => 'omitted', 'source_value' => null];
                    }
                }
                $catalog = [
                    'code' => $code, 'name' => $definition['name'], 'unit' => $definition['unit'],
                    'source' => 'IBGE - Censo Demográfico', 'source_type' => 'ibge_aggregates',
                    'source_aggregate_id' => $aggregate, 'source_variable_id' => $definition['variable'],
                    'source_research_id' => null, 'source_indicator_id' => null,
                    'source_methodological_reference' => 'Censo Demográfico 2022', 'source_series' => null,
                    'source_filters' => json_encode(['classifications' => array_map(fn ($id) => ['id' => $id, 'categories' => $definition['filters'][$id]], array_keys($definition['filters']))], JSON_THROW_ON_ERROR),
                    'source_universe' => $definition['universe'], 'source_definition' => $definition['definition'],
                ];
                DB::transaction(function () use ($catalog, $rows, $municipalities, $year): void {
                    Indicator::upsert([$catalog], ['code'], array_diff(array_keys($catalog), ['code']));
                    $id = Indicator::where('code', $catalog['code'])->value('id');
                    $now = now();
                    $values = [];
                    foreach ($rows as $ibge => $row) {
                        $values[] = [...$row, 'indicator_id' => $id, 'municipality_id' => $municipalities[$ibge], 'reference_year' => $year, 'last_synced_at' => $now];
                    }
                    foreach (array_chunk($values, 500) as $chunk) {
                        MunicipalityIndicatorValue::upsert($chunk, ['municipality_id', 'indicator_id', 'reference_year'], ['value', 'status', 'source_value', 'last_synced_at']);
                    }
                });
                $counts[$code] = array_count_values(array_column($rows, 'status'));
            }
        }

        return [...$reader->metrics(), 'counts' => $counts, 'seconds' => round(microtime(true) - $started, 2), 'peak_memory_mb' => round(memory_get_peak_usage(true) / 1048576, 2)];
    }
}
