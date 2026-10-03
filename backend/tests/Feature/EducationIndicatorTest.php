<?php

namespace Tests\Feature;

use App\Models\Indicator;
use App\Models\MunicipalityIndicatorValue;
use App\Models\State;
use App\Services\EducationIndicatorSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
use ZipArchive;

class EducationIndicatorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        $state = State::create(['ibge_code' => 32, 'name' => 'Espírito Santo', 'abbreviation' => 'ES']);
        foreach ([3205309 => 'Vitória', 3200102 => 'Afonso Cláudio', 3550308 => 'São Paulo', 5101837 => 'Boa Esperança do Norte'] as $ibge => $name) {
            $state->municipalities()->create(['ibge_code' => $ibge, 'name' => $name]);
        }
        config(['education.census_rows' => 4, 'education.indicators.ideb_initial_years_public.rows' => 4, 'education.indicators.ideb_final_years_public.rows' => 4]);
    }

    private function sheets(): array
    {
        $result = [];
        $values = ['1.1' => [77662, 5805, 2512355, 1735], '2.1' => [5251, 387, 136960, 81], '3.1' => [178, 37, 7187, 7]];
        foreach (config('education.indicators') as $definition) {
            if (isset($definition['table'])) {
                $table = $definition['table'];
                $rows = [[$table.' – Número de '.$definition['measure'].' da Educação Básica, por Etapa de Ensino, segundo o Município - 2025'],
                    ['Região Geográfica', 'Unidade da Federação', 'Município', 'Código do Município', 'Número'],
                    [null, null, null, null, 'Total', 'Etapas'], ['Brasil', null, null, null, 999999]];
                foreach ([3205309, 3200102, 3550308, 5101837] as $i => $ibge) {
                    $rows[] = ['Região', 'UF', 'Nome não utilizado', $ibge, $values[$table][$i], 99999];
                }
                $rows[] = ['Fonte: INEP', null, null, ' '];
                $result['Educação Básica '.$table] = $rows;
            } else {
                $ai = $definition['stage'] === 'Anos Iniciais';
                $rows = [['Ensino Fundamental Regular - '.$definition['stage']], ['Indicadores de 2005 a 2025'],
                    ['SG_UF', 'CO_MUNICIPIO', 'NO_MUNICIPIO', 'REDE', ...array_map(fn ($year) => 'VL_OBSERVADO_'.$year, range(2005, 2025, 2))]];
                $series = $ai ? [4, 4.2, 4.8, 4.9, 5, 5.6, 5.6, 5.6, 5.6, 6.1, 6.4] : [3.4, 3.5, 3.8, 4.1, 4, 4.1, 4.3, 4.6, 4.6, 4.9, 5.3];
                $rows[] = ['ES', 3205309, 'Vitória', 'Pública', ...$series];
                $rows[] = ['ES', 3205309, 'Vitória', 'Municipal', ...array_fill(0, 11, 9)];
                $rows[] = ['ES', 3200102, 'Afonso Cláudio', 'Pública', ...array_fill(0, 10, '-'), $ai ? 7.1 : 5.8];
                $rows[] = ['SP', 3550308, 'São Paulo', 'Pública', ...array_fill(0, 10, '-'), $ai ? 6.2 : 5];
                $rows[] = ['MT', 5101837, 'Boa Esperança do Norte', 'Pública', ...array_fill(0, 10, '-'), $ai ? 6.7 : 5];
                $rows[] = ['Fonte: MEC/INEP'];
                $result[$definition['sheet']] = $rows;
            }
        }

        return $result;
    }

    private function archive(array $sheets, bool $badChecksum = false): string
    {
        $path = tempnam(sys_get_temp_dir(), 'education-test-');
        $writer = new Writer;
        try {
            $writer->openToFile($path);
            $first = true;
            foreach ($sheets as $name => $rows) {
                $sheet = $first ? $writer->getCurrentSheet() : $writer->addNewSheetAndMakeItCurrent();
                $first = false;
                $sheet->setName($name);
                foreach ($rows as $row) {
                    $writer->addRow(Row::fromValues($row));
                }
            }
            $writer->close();
            $zip = new ZipArchive;
            $zip->open($path.'.zip', ZipArchive::CREATE);
            $zip->addFile($path, 'dados/Educação.xlsx');
            $manifest = ($badChecksum ? str_repeat('0', 32) : md5_file($path)).' *Educação.xlsx';
            $zip->addFromString('md5.txt', mb_convert_encoding($manifest, 'Windows-1252', 'UTF-8'));
            $zip->close();

            return file_get_contents($path.'.zip');
        } finally {
            @unlink($path);
            @unlink($path.'.zip');
        }
    }

    private function fake(?callable $modify = null, bool $badChecksum = false): void
    {
        $sheets = $this->sheets();
        if ($modify) {
            $modify($sheets);
        }
        $census = array_filter($sheets, fn ($name) => str_starts_with($name, 'Educação'), ARRAY_FILTER_USE_KEY);
        $payloads = [config('education.census_url') => $this->archive($census, $badChecksum)];
        foreach (config('education.indicators') as $definition) {
            if (isset($definition['sheet'])) {
                $payloads[$definition['url']] = $this->archive([$definition['sheet'] => $sheets[$definition['sheet']]], $badChecksum);
            }
        }
        Http::swap(new Factory);
        Http::preventStrayRequests();
        Http::fake(function ($request) use ($payloads) {
            $body = $payloads[$request->url()];

            return Http::response($body, 200, ['Content-Length' => strlen($body)]);
        });
    }

    public function test_imports_official_totals_series_metadata_api_and_idempotence(): void
    {
        $this->fake();
        $service = app(EducationIndicatorSyncService::class);
        $service->sync('census', 2025);
        $service->sync('ideb', 2025);
        $this->assertDatabaseCount('indicators', 5);
        $this->assertDatabaseCount('municipality_indicator_values', 100);
        $response = $this->getJson('/api/municipalities/3205309/indicators')->assertOk()->assertJsonCount(25, 'indicators');
        $indicators = collect($response->json('indicators'));
        $this->assertSame('77662.000000', $indicators->firstWhere('code', 'basic_education_enrollments')['value']);
        $this->assertSame('5251.000000', $indicators->firstWhere('code', 'basic_education_teachers')['value']);
        $this->assertSame('6.400000', $indicators->firstWhere('code', 'ideb_initial_years_public')['value']);
        $this->assertSame(['code', 'name', 'value', 'unit', 'reference_year'], array_keys($indicators->first()));
        $this->getJson('/api/municipalities/5101837/indicators')->assertOk()->assertJsonCount(5, 'indicators');
        $this->assertSame(60, MunicipalityIndicatorValue::where('status', 'unavailable')->whereNull('value')->where('source_value', '-')->count());
        foreach (Indicator::all() as $indicator) {
            $this->assertNull($indicator->source_variable_id);
            $this->assertNull($indicator->source_aggregate_id);
            $this->assertNull($indicator->source_research_id);
            $this->assertNull($indicator->source_indicator_id);
            $this->assertNotEmpty($indicator->source_definition);
            $this->assertNotEmpty($indicator->source_universe);
            $this->assertNotEmpty($indicator->source_filters);
        }
        $before = MunicipalityIndicatorValue::orderBy('id')->get(['id', 'created_at', 'value', 'status', 'source_value'])->toArray();
        $this->travel(1)->hours();
        $this->fake();
        $service->sync('census', 2025);
        $service->sync('ideb', 2025);
        $this->assertSame($before, MunicipalityIndicatorValue::orderBy('id')->get(['id', 'created_at', 'value', 'status', 'source_value'])->toArray());
    }

    public static function invalidCensus(): array
    {
        return array_map(fn ($v) => [$v], ['negative', 'unknown', 'short', 'duplicate', 'year', 'total', 'sheet', 'checksum', 'missing', 'header', 'table']);
    }

    #[DataProvider('invalidCensus')]
    public function test_invalid_census_prevents_all_publication(string $case): void
    {
        $this->fake(function (&$sheets) use ($case) {
            $s = &$sheets['Educação Básica 2.1'];
            match ($case) {
                'negative' => $s[4][4] = -1,
                'unknown' => $s[4][3] = 9999999,
                'short' => $s[4][3] = 320530,
                'duplicate' => $s[5][3] = 3205309,
                'year' => $s[0][0] = str_replace('2025', '2024', $s[0][0]),
                'total' => $s[2][4] = 'Subtotal',
                'sheet' => $sheets['Outra'] = array_splice($sheets, 1, 1),
                'missing' => array_splice($s, 4, 1),
                'header' => $s[1][3] = 'Código da Escola',
                'table' => $s[0][0] = str_replace('2.1', '2.2', $s[0][0]),
                default => null,
            };
        }, $case === 'checksum');
        $this->artisan('indicators:sync-school-census')->assertFailed();
        $this->assertDatabaseCount('indicators', 0);
        $this->assertDatabaseCount('municipality_indicator_values', 0);
    }

    public static function invalidIdeb(): array
    {
        return array_map(fn ($v) => [$v], ['marker', 'duplicate', 'network', 'even', 'year', 'stage', 'blank', 'range', 'sheet', 'header', 'code']);
    }

    #[DataProvider('invalidIdeb')]
    public function test_invalid_ideb_prevents_publication_of_both_stages(string $case): void
    {
        $this->fake(function (&$sheets) use ($case) {
            $s = &$sheets['IDEB_AF_MUNICÍPIOS'];
            match ($case) {
                'marker' => $s[3][4] = '?',
                'duplicate' => $s[5][1] = 3205309,
                'network' => $s[3][3] = 'Total',
                'even' => $s[2][4] = 'VL_OBSERVADO_2006',
                'year' => $s[1][0] = '2005 a 2023',
                'stage' => $s[0][0] = 'Ensino Médio',
                'blank' => $s[3][4] = null,
                'range' => $s[3][4] = 11,
                'sheet' => $sheets['IDEB_AF_MUNICÍPIOS'] = [],
                'header' => $s[2][0] = 'CO_MUNICIPIO',
                'code' => $s[3][1] = '0320530',
            };
        });
        $this->artisan('indicators:sync-ideb')->assertFailed();
        $this->assertDatabaseCount('indicators', 0);
    }

    public function test_zero_known_markers_revisions_and_disappearing_rows(): void
    {
        $this->fake();
        $service = app(EducationIndicatorSyncService::class);
        $service->sync('ideb', 2025);
        $this->fake(function (&$sheets) {
            $sheets['IDEB_AI_MUNICÍPIOS'][3][4] = 'ND*';
            $sheets['IDEB_AI_MUNICÍPIOS'][3][5] = 0;
            $sheets['IDEB_AI_MUNICÍPIOS'][3][6] = 4.9;
            $sheets['IDEB_AI_MUNICÍPIOS'][3][7] = 'ND';
            $sheets['IDEB_AI_MUNICÍPIOS'][3][8] = 'ND***';
            $sheets['Educação Básica 1.1'][4][4] = 0;
        });
        $result = $service->sync('ideb', 2025);
        $service->sync('census', 2025);
        $this->assertSame(5, $result['counts']['ideb_initial_years_public']['changed']);
        $this->assertDatabaseHas('municipality_indicator_values', ['source_value' => 'ND*', 'status' => 'unavailable', 'value' => null]);
        $this->assertDatabaseHas('municipality_indicator_values', ['source_value' => 'ND', 'status' => 'unavailable', 'value' => null]);
        $this->assertDatabaseHas('municipality_indicator_values', ['source_value' => 'ND***', 'status' => 'unavailable', 'value' => null]);
        $this->assertDatabaseHas('municipality_indicator_values', ['source_value' => '0', 'status' => 'available', 'value' => 0]);
        $before = MunicipalityIndicatorValue::orderBy('id')->get()->toArray();
        $this->fake(function (&$sheets) {
            array_splice($sheets['IDEB_AI_MUNICÍPIOS'], 7, 1);
        });
        config(['education.indicators.ideb_initial_years_public.rows' => 3]);
        $this->artisan('indicators:sync-ideb')->assertFailed();
        $this->assertSame($before, MunicipalityIndicatorValue::orderBy('id')->get()->toArray());
    }

    public function test_missing_network_rows_are_not_materialized_and_other_indicators_survive(): void
    {
        $this->fake();
        $service = app(EducationIndicatorSyncService::class);
        $service->sync('census', 2025);
        $before = MunicipalityIndicatorValue::orderBy('id')->get()->toArray();
        $this->fake(function (&$sheets) {
            array_splice($sheets['IDEB_AI_MUNICÍPIOS'], 7, 1);
        });
        config(['education.indicators.ideb_initial_years_public.rows' => 3]);
        $service->sync('ideb', 2025);
        $this->assertSame($before, MunicipalityIndicatorValue::orderBy('id')->limit(12)->get()->toArray());
        $this->assertDatabaseCount('municipality_indicator_values', 89);
        $this->getJson('/api/municipalities/5101837/indicators')->assertOk()->assertJsonCount(4, 'indicators');
    }

    public function test_database_failure_rolls_back_the_entire_family(): void
    {
        $this->fake();
        DB::unprepared("CREATE TRIGGER education_test_failure BEFORE INSERT ON municipality_indicator_values WHEN NEW.value = 5251 BEGIN SELECT RAISE(ABORT, 'Simulated storage failure'); END");
        $this->artisan('indicators:sync-school-census')->assertFailed();
        $this->assertDatabaseCount('indicators', 0);
        $this->assertDatabaseCount('municipality_indicator_values', 0);
    }

    public function test_rejects_unsupported_command_options_without_http(): void
    {
        $this->artisan('indicators:sync-school-census', ['--year' => '2024'])->assertFailed();
        $this->artisan('indicators:sync-ideb', ['--edition' => '2023'])->assertFailed();
        $this->artisan('indicators:sync-ideb', ['--network' => 'municipal'])->assertFailed();
        Http::assertNothingSent();
    }
}
