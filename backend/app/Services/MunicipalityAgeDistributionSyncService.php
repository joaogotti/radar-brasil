<?php

namespace App\Services;

use App\Models\AgeGroup;
use App\Models\Municipality;
use App\Models\MunicipalityAgeSexValue;
use App\Support\CensusSyncLock;
use App\Support\IbgeCensusValueParser;
use Database\Seeders\AgeGroupSeeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class MunicipalityAgeDistributionSyncService
{
    public function sync(): array
    {
        return app(CensusSyncLock::class)->run('age-distribution', fn () => $this->synchronize());
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
        $source = config('demography.pyramid');
        $ages = array_column(config('demography.age_groups'), 'ibge_code');
        $sexes = array_keys(config('demography.sexes'));
        $snapshot = [];
        $coverage = null;
        foreach ($source['variables'] as $measure => $variable) {
            foreach ($sexes as $sex) {
                foreach (array_chunk($ages, 3) as $batch) {
                    $filters = [$source['classifications']['sex'] => [$sex], $source['classifications']['age'] => $batch, $source['classifications']['declaration'] => [$source['declaration_total']]];
                    $payload = $reader->read($source['aggregate'], [$variable => $source['units'][$measure]], $filters, $municipalities);
                    foreach ($payload[$variable] as $result) {
                        $age = $result['categories'][$source['classifications']['age']];
                        $keys = array_keys($result['values']);
                        if ($coverage !== null && $coverage !== $keys) {
                            throw new RuntimeException('Inconsistent coverage between age/sex cells or measures.');
                        }
                        $coverage = $keys;
                        if (isset($snapshot[$measure][$sex][$age])) {
                            throw new RuntimeException('Duplicate age/sex cell.');
                        }
                        foreach ($result['values'] as $raw) {
                            $parser->parse($raw, $measure);
                        }
                        $snapshot[$measure][$sex][$age] = $result['values'];
                    }
                }
            }
        }
        $missing = array_diff(array_keys($municipalities), $coverage ?? []);
        $previouslyAvailable = MunicipalityAgeSexValue::where('reference_year', $year)
            ->where(fn ($q) => $q->where('population_status', 'available')->orWhere('percentage_status', 'available'))
            ->distinct()->pluck('municipality_id')->all();
        foreach ($missing as $ibge) {
            if (in_array((int) $ibge, config('demography.known_omissions'), true) && ! in_array($municipalities[$ibge], $previouslyAvailable, true)) {
                continue;
            }
            $filters = [$source['classifications']['sex'] => $sexes, $source['classifications']['age'] => $ages, $source['classifications']['declaration'] => [$source['declaration_total']]];
            $variables = [];
            foreach ($source['variables'] as $measure => $variable) {
                $variables[$variable] = $source['units'][$measure];
            }
            $confirmation = $reader->read($source['aggregate'], $variables, $filters, $municipalities, [$ibge]);
            foreach ($source['variables'] as $measure => $variable) {
                foreach ($confirmation[$variable] as $result) {
                    if (! array_key_exists($ibge, $result['values'])) {
                        throw new RuntimeException('Unresolved age-distribution omission: '.$ibge);
                    }
                    $sex = $result['categories'][$source['classifications']['sex']];
                    $age = $result['categories'][$source['classifications']['age']];
                    $parser->parse($result['values'][$ibge], $measure);
                    $snapshot[$measure][$sex][$age][$ibge] = $result['values'][$ibge];
                }
            }
        }
        $counts = ['population' => [], 'percentage' => []];
        $total = 0;
        DB::transaction(function () use ($snapshot, $municipalities, $sexes, $ages, $year, $parser, &$counts, &$total): void {
            app(AgeGroupSeeder::class)->run();
            $ageIds = AgeGroup::pluck('id', 'ibge_code')->all();
            $now = now();
            $chunk = [];
            foreach ($municipalities as $ibge => $municipalityId) {
                foreach ($sexes as $sex) {
                    foreach ($ages as $age) {
                        $row = ['municipality_id' => $municipalityId, 'reference_year' => $year, 'sex_code' => $sex, 'age_group_id' => $ageIds[$age], 'last_synced_at' => $now];
                        foreach (['population', 'percentage'] as $measure) {
                            $parsed = array_key_exists($ibge, $snapshot[$measure][$sex][$age])
                                ? $parser->parse($snapshot[$measure][$sex][$age][$ibge], $measure)
                                : ['value' => null, 'status' => 'omitted', 'source_value' => null];
                            $row[$measure] = $parsed['value'];
                            $row[$measure.'_status'] = $parsed['status'];
                            $row[$measure.'_source_value'] = $parsed['source_value'];
                            $counts[$measure][$parsed['status']] = ($counts[$measure][$parsed['status']] ?? 0) + 1;
                        }
                        $chunk[] = $row;
                        $total++;
                        if (count($chunk) === 500) {
                            $this->persist($chunk);
                            $chunk = [];
                        }
                    }
                }
            }
            if ($chunk !== []) {
                $this->persist($chunk);
            }
        });

        return [...$reader->metrics(), 'counts' => $counts, 'rows' => $total, 'seconds' => round(microtime(true) - $started, 2), 'peak_memory_mb' => round(memory_get_peak_usage(true) / 1048576, 2)];
    }

    private function persist(array $rows): void
    {
        MunicipalityAgeSexValue::upsert($rows, ['municipality_id', 'reference_year', 'sex_code', 'age_group_id'], ['population', 'percentage', 'population_status', 'percentage_status', 'population_source_value', 'percentage_source_value', 'last_synced_at']);
    }
}
