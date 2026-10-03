<?php

namespace App\Services;

use App\Models\Indicator;
use App\Models\Municipality;
use App\Models\MunicipalityIndicatorValue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class EducationIndicatorSyncService
{
    public function __construct(private InepEducationArchive $archive, private InepEducationReader $reader) {}

    public function sync(string $family, int $year, string $network = 'public', ?callable $progress = null): array
    {
        if (! in_array($family, ['census', 'ideb'], true) || $year !== 2025 || $network !== 'public') {
            throw new RuntimeException('Only school census 2025 and IDEB edition 2025/public are supported.');
        }
        $lock = fopen(storage_path('app/education-'.$family.'.lock'), 'c');
        if ($lock === false) {
            throw new RuntimeException('Cannot open education synchronization lock.');
        }
        try {
            if (! flock($lock, LOCK_EX | LOCK_NB)) {
                throw new RuntimeException('An education synchronization is already running.');
            }

            return $this->synchronize($family, $progress);
        } finally {
            fclose($lock);
        }
    }

    private function synchronize(string $family, ?callable $progress): array
    {
        $start = microtime(true);
        $municipalities = Municipality::pluck('id', 'ibge_code')->all();
        if ($municipalities === []) {
            throw new RuntimeException('Register municipalities before synchronizing education.');
        }
        $definitions = array_filter(config('education.indicators'), fn ($d) => ($family === 'census') === isset($d['table']));
        $artifacts = [];
        $data = [];
        if ($family === 'census') {
            $progress && $progress('INEP: download e leitura da Sinopse 2025.');
            $result = $this->archive->read(config('education.census_url'), fn ($path) => $this->reader->census($path, $municipalities));
            $data = $result['data'];
            $artifacts[] = $result['artifact'];
        } else {
            foreach ($definitions as $code => $definition) {
                $progress && $progress('INEP: download e leitura '.$code.' (2005–2025, Pública).');
                $result = $this->archive->read($definition['url'], fn ($path) => $this->reader->ideb($path, $definition, $municipalities));
                $data[$code] = $result['data'];
                $artifacts[] = $result['artifact'];
            }
        }
        $validationStarted = microtime(true);
        $counts = [];
        $revisions = [];
        $codesById = array_flip($municipalities);
        foreach ($data as $code => $municipalRows) {
            $counts[$code] = ['municipalities' => count($municipalRows), 'available' => 0, 'unavailable' => 0, 'inserted' => 0, 'existing' => 0, 'changed' => 0, 'available_2025' => 0];
            foreach ($municipalRows as $ibge => $years) {
                foreach ($years as $year => $row) {
                    $counts[$code][$row['status']]++;
                    $counts[$code]['inserted']++;
                    if ($year === 2025 && $row['status'] === 'available') {
                        $counts[$code]['available_2025']++;
                    }
                }
            }
            $existing = MunicipalityIndicatorValue::whereHas('indicator', fn ($q) => $q->where('code', $code))
                ->whereIn('reference_year', $family === 'census' ? [2025] : config('education.years'));
            foreach ($existing->lazyById(500) as $old) {
                $ibge = $codesById[$old->municipality_id];
                $row = $municipalRows[$ibge][$old->reference_year] ?? null;
                if ($row === null) {
                    throw new RuntimeException('Previously imported INEP observation disappeared: '.$code.'/'.$ibge.'/'.$old->reference_year.'. Publication refused; review the source.');
                }
                $counts[$code]['existing']++;
                $counts[$code]['inserted']--;
                $sameValue = $old->value === $this->decimal($row['value']);
                if (! $sameValue || $old->status !== $row['status'] || $old->source_value !== $row['source_value']) {
                    $counts[$code]['changed']++;
                    $revisions[] = ['code' => $code, 'ibge_code' => $ibge, 'year' => $old->reference_year,
                        'before' => $old->only(['value', 'status', 'source_value']), 'after' => $row];
                }
            }
        }
        $progress && $progress('Cobertura validada: '.json_encode($counts, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        $run = bin2hex(random_bytes(12));
        Log::info('INEP education validated', ['run' => $run, 'artifacts' => $artifacts, 'counts' => $counts, 'revisions' => $revisions]);
        $validationSeconds = microtime(true) - $validationStarted;
        $persistStart = microtime(true);
        DB::transaction(function () use ($data, $definitions, $municipalities, $family): void {
            foreach ($data as $code => $rows) {
                $catalog = $this->catalog($code, $definitions[$code], $family);
                Indicator::upsert([$catalog], ['code'], array_diff(array_keys($catalog), ['code']));
                $id = Indicator::where('code', $code)->value('id');
                $now = now();
                $batch = [];
                foreach ($rows as $ibge => $years) {
                    foreach ($years as $year => $row) {
                        $batch[] = [...$row, 'municipality_id' => $municipalities[$ibge], 'indicator_id' => $id, 'reference_year' => $year, 'last_synced_at' => $now];
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
        $report = ['run' => $run, 'family' => $family, 'edition' => 2025, 'counts' => $counts, 'artifacts' => $artifacts,
            'requests' => array_sum(array_column($artifacts, 'requests')), 'retries' => array_sum(array_column($artifacts, 'retries')),
            'validation_seconds' => round($validationSeconds, 2),
            'persist_seconds' => round(microtime(true) - $persistStart, 2), 'seconds' => round(microtime(true) - $start, 2),
            'peak_memory_mb' => round(memory_get_peak_usage(true) / 1048576, 2)];
        Log::info('INEP education committed', $report);

        return $report;
    }

    private function write(array $batch): void
    {
        MunicipalityIndicatorValue::upsert($batch, ['municipality_id', 'indicator_id', 'reference_year'], ['value', 'status', 'source_value', 'last_synced_at']);
    }

    private function decimal(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        [$integer, $fraction] = array_pad(explode('.', $value), 2, '');

        return $integer.'.'.str_pad($fraction, 6, '0');
    }

    private function catalog(string $code, array $definition, string $family): array
    {
        $census = $family === 'census';

        return [
            'code' => $code, 'name' => $definition['name'], 'unit' => $definition['unit'],
            'source' => $census ? 'INEP — Censo Escolar da Educação Básica' : 'INEP — IDEB',
            'source_type' => $census ? 'inep_census_synopsis' : 'inep_ideb',
            'source_series' => $census ? 'Sinopse Estatística da Educação Básica' : 'IDEB observado — metodologia original',
            'source_aggregate_id' => null, 'source_variable_id' => null, 'source_research_id' => null, 'source_indicator_id' => null,
            'source_methodological_reference' => $census ? 'Notas da tabela '.$definition['table'].' da Sinopse 2025; referência 28/05/2025' : 'Nota Informativa IDEB 2025; metodologia original; ressalvas de participação, pandemia em 2021 e exclusão federal em 2011',
            'source_filters' => json_encode($census
                ? ['table' => $definition['table'], 'column' => 'Total', 'dependencies' => 'Todas', 'location' => ['Urbana', 'Rural'], 'excluded' => ['Atividade Complementar', 'AEE']]
                : ['stage' => $definition['stage'], 'network' => 'Pública', 'aggregation' => 'Município', 'performance_location' => 'Urbana', 'columns' => 'VL_OBSERVADO_*'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            'source_universe' => $definition['universe'] ?? 'Rede pública no município, '.$definition['stage'].' do ensino fundamental regular; componente de desempenho das escolas urbanas, conforme critérios oficiais de participação.',
            'source_definition' => $definition['definition'] ?? 'Índice oficial que combina desempenho no Saeb e aprovação escolar. Importado sem recálculo, média entre escolas, interpolação ou substituição por outra rede.',
        ];
    }
}
