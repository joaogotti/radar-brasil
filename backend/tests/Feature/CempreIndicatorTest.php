<?php

namespace Tests\Feature;

use App\Models\Indicator;
use App\Models\Municipality;
use App\Models\MunicipalityIndicatorValue;
use App\Models\State;
use App\Services\CempreIndicatorSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CempreIndicatorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $es = State::create(['ibge_code' => 32, 'name' => 'Espírito Santo', 'abbreviation' => 'ES']);
        $mt = State::create(['ibge_code' => 51, 'name' => 'Mato Grosso', 'abbreviation' => 'MT']);
        $es->municipalities()->create(['ibge_code' => 3205309, 'name' => 'Vitória']);
        $mt->municipalities()->create(['ibge_code' => 5101837, 'name' => 'Boa Esperança do Norte']);
        Http::preventStrayRequests();
    }

    private function fake(?callable $change = null): void
    {
        Http::swap(new Factory);
        Http::preventStrayRequests();
        $fixture = json_decode(file_get_contents(base_path('tests/Fixtures/cempre-2024.json')), true, flags: JSON_THROW_ON_ERROR);
        Http::fake(function (Request $request) use ($fixture, $change) {
            $this->assertMatchesRegularExpression('~^https://apisidra\.ibge\.gov\.br/values/t/9509/n6/(all|5101837)/v/706,367,707,708,10143/p/(202[234])(?:,202[234])*$~', $request->url());
            preg_match('~/n6/([^/]+)/.*?/p/(.+)$~', $request->url(), $match);
            $payload = [$fixture[0]];
            foreach (explode(',', $match[2]) as $year) {
                foreach (array_slice($fixture, 1) as $row) {
                    $row['D3C'] = $row['D3N'] = $year;
                    if ($match[1] !== 'all') {
                        $row['D1C'] = $match[1];
                        $row['D1N'] = 'Boa Esperança do Norte (MT)';
                        $row['V'] = '...';
                    }
                    $payload[] = $row;
                }
            }

            return $change ? $change($payload, $request) : Http::response($payload);
        });
    }

    public function test_catalog_values_contract_and_idempotency(): void
    {
        $other = Indicator::create(['code' => 'existing', 'name' => 'Existing', 'unit' => 'Pessoas', 'source' => 'IBGE', 'source_type' => 'ibge_aggregates']);
        $otherValue = $other->municipalityValues()->create(['municipality_id' => Municipality::where('ibge_code', 3205309)->value('id'), 'reference_year' => 2022, 'value' => '12']);
        $beforeOther = [$other->fresh()->toArray(), $otherValue->fresh()->toArray()];
        $this->fake();
        $service = app(CempreIndicatorSyncService::class);
        $result = $service->sync();
        $this->assertSame(4, $result['requests']);
        $this->assertSame(0, $result['retries']);
        $this->assertSame(30, $result['total']);
        Http::assertSent(fn (Request $r) => str_contains($r->url(), '/n6/5101837/') && str_ends_with($r->url(), '/p/2022,2023,2024'));
        $expected = [706 => ['cempre_local_units', 'Unidades locais', 'Unidades'], 367 => ['cempre_active_organizations', 'Empresas e organizações atuantes', 'Unidades'],
            707 => ['cempre_employed_personnel', 'Pessoal ocupado total', 'Pessoas'], 708 => ['cempre_salaried_personnel', 'Pessoal ocupado assalariado', 'Pessoas'],
            10143 => ['cempre_average_monthly_salary', 'Salário médio mensal', 'Reais']];
        foreach ($expected as $variable => [$code, $name, $unit]) {
            $i = Indicator::where('code', $code)->firstOrFail();
            $this->assertSame([$name, $unit, 9509, $variable, 'ibge_aggregates'], [$i->name, $i->unit, $i->source_aggregate_id, $i->source_variable_id, $i->source_type]);
            $this->assertSame(['territorial_level' => 'N6', 'classifications' => []], $i->source_filters);
            $this->assertStringContainsString('2022', $i->source_series);
            $this->assertStringContainsString('1685', $i->source_methodological_reference);
            $this->assertStringContainsString('https://sidra.ibge.gov.br/tabela/9509', $i->source_definition);
            $this->assertStringContainsString('exclui MEIs', $i->source_universe);
            $this->assertNull($i->source_research_id);
            $this->assertSame(1, $result['counts'][2024][$code]['unavailable']);
        }
        $response = $this->getJson('/api/municipalities/3205309/indicators')->assertOk()->assertJsonCount(16, 'indicators');
        $salary = collect($response->json('indicators'))->firstWhere('code', 'cempre_average_monthly_salary');
        $this->assertSame('5375.960000', $salary['value']);
        $this->assertSame(2024, $salary['reference_year']);
        $this->assertSame(['code', 'name', 'value', 'unit', 'reference_year'], array_keys($salary));
        $this->getJson('/api/municipalities/5101837/indicators')->assertOk()->assertJsonCount(0, 'indicators');
        $before = MunicipalityIndicatorValue::orderBy('id')->get(['id', 'created_at', 'value', 'status', 'source_value'])->toArray();
        $catalog = Indicator::orderBy('id')->get(['id', 'code', 'created_at'])->toArray();
        Indicator::where('code', 'cempre_local_units')->update(['name' => 'Outdated', 'source_definition' => 'Outdated']);
        $this->travel(1)->hours();
        $service->sync();
        $updated = Indicator::where('code', 'cempre_local_units')->firstOrFail();
        $this->assertSame('Unidades locais', $updated->name);
        $this->assertStringContainsString('Locais de atuação', $updated->source_definition);
        $this->assertSame($before, MunicipalityIndicatorValue::orderBy('id')->get(['id', 'created_at', 'value', 'status', 'source_value'])->toArray());
        $this->assertSame($catalog, Indicator::orderBy('id')->get(['id', 'code', 'created_at'])->toArray());
        $this->assertSame($beforeOther, [$other->fresh()->toArray(), $otherValue->fresh()->toArray()]);
        $this->assertDatabaseCount('indicators', 6);
        $this->assertDatabaseCount('municipality_indicator_values', 31);
    }

    public function test_symbols_revisions_and_original_strings(): void
    {
        $this->fake();
        app(CempreIndicatorSyncService::class)->sync();
        $old = MunicipalityIndicatorValue::orderBy('id')->get(['id', 'created_at'])->toArray();
        $this->fake(function ($payload, $request) {
            if (str_contains($request->url(), '/n6/all/')) {
                foreach (['-', '0', 'X', '..', '...'] as $index => $value) {
                    $payload[$index + 1]['V'] = $value;
                }
            }

            return Http::response($payload);
        });
        app(CempreIndicatorSyncService::class)->sync();
        foreach (['cempre_local_units' => ['0.000000', 'available', '-'], 'cempre_active_organizations' => ['0.000000', 'available', '0'],
            'cempre_employed_personnel' => [null, 'suppressed', 'X'], 'cempre_salaried_personnel' => [null, 'not_applicable', '..'],
            'cempre_average_monthly_salary' => [null, 'unavailable', '...']] as $code => $expected) {
            $row = MunicipalityIndicatorValue::whereHas('indicator', fn ($q) => $q->where('code', $code))->whereHas('municipality', fn ($q) => $q->where('ibge_code', 3205309))->firstOrFail();
            $this->assertSame($expected, [$row->value, $row->status, $row->source_value]);
        }
        $this->fake();
        app(CempreIndicatorSyncService::class)->sync();
        $this->assertSame($old, MunicipalityIndicatorValue::orderBy('id')->get(['id', 'created_at'])->toArray());
        $this->assertDatabaseHas('municipality_indicator_values', ['source_value' => '5375.96', 'status' => 'available']);
    }

    public static function invalidCases(): array
    {
        return array_map(fn ($case) => [$case], ['header', 'structure', 'level', 'code', 'unknown', 'variable', 'missing_variable', 'partial_variable', 'year', 'duplicate', 'unit', 'unit_id', 'symbol', 'precision', 'null']);
    }

    #[DataProvider('invalidCases')]
    public function test_invalid_final_year_never_publishes_partial_series(string $case): void
    {
        $this->fake(function ($payload, $request) use ($case) {
            if (str_ends_with($request->url(), '/p/2024')) {
                switch ($case) {
                    case 'header': $payload[0]['D2C'] = 'Other';
                        break;
                    case 'structure': $payload = ['error' => 'invalid'];
                        break;
                    case 'level': $payload[1]['NC'] = '3';
                        break;
                    case 'code': $payload[1]['D1C'] = '320530';
                        break;
                    case 'unknown': $payload[1]['D1C'] = '9999999';
                        break;
                    case 'variable': $payload[1]['D2C'] = '662';
                        break;
                    case 'missing_variable': array_pop($payload);
                        break;
                    case 'partial_variable': $row = $payload[1];
                        $row['D1C'] = '5101837';
                        $payload[] = $row;
                        break;
                    case 'year': $payload[1]['D3C'] = $payload[1]['D3N'] = '2021';
                        break;
                    case 'duplicate': $payload[] = $payload[1];
                        break;
                    case 'unit': $payload[5]['MN'] = 'Mil Reais';
                        break;
                    case 'unit_id': $payload[5]['MC'] = '40';
                        break;
                    case 'symbol': $payload[1]['V'] = '?';
                        break;
                    case 'precision': $payload[5]['V'] = '1.1234567';
                        break;
                    case 'null': $payload[1]['V'] = null;
                        break;
                }
            }

            return Http::response($payload);
        });
        $this->artisan('indicators:sync-cempre')->assertFailed();
        $this->assertDatabaseCount('indicators', 0);
        $this->assertDatabaseCount('municipality_indicator_values', 0);
    }

    public function test_failed_confirmation_preserves_existing_catalog_and_values(): void
    {
        $this->fake();
        app(CempreIndicatorSyncService::class)->sync();
        $before = [Indicator::all()->toArray(), MunicipalityIndicatorValue::all()->toArray()];
        $this->fake(fn ($payload, $r) => str_contains($r->url(), '/n6/5101837/') ? Http::response([], 503) : Http::response($payload));
        $this->artisan('indicators:sync-cempre')->assertFailed();
        Http::assertSentCount(6);
        $this->assertSame($before, [Indicator::all()->toArray(), MunicipalityIndicatorValue::all()->toArray()]);
    }

    public function test_incomplete_confirmation_fails_without_publishing(): void
    {
        $this->fake(function ($payload, $r) {
            if (str_contains($r->url(), '/n6/5101837/')) {
                $payload = array_values(array_filter($payload, fn ($row) => $row['D3C'] !== '2023'));
            }

            return Http::response($payload);
        });
        $this->artisan('indicators:sync-cempre')->assertFailed();
        $this->assertDatabaseCount('indicators', 0);
    }

    public function test_retries_only_transient_failures_and_uses_ids_not_order(): void
    {
        $attempts = 0;
        $this->fake(function ($payload) use (&$attempts) {
            if (++$attempts < 3) {
                return Http::response([], 429);
            }

            return Http::response([$payload[0], ...array_reverse(array_slice($payload, 1))]);
        });
        $result = app(CempreIndicatorSyncService::class)->sync();
        $this->assertSame(6, $result['requests']);
        $this->assertSame(2, $result['retries']);
        $this->fake(fn () => Http::response([], 400));
        $this->artisan('indicators:sync-cempre')->assertFailed();
        Http::assertSentCount(1);
    }

    public function test_database_failure_rolls_back_catalog_and_previous_batches(): void
    {
        $this->fake();
        DB::unprepared("CREATE TRIGGER cempre_failure BEFORE INSERT ON municipality_indicator_values WHEN NEW.indicator_id IN (SELECT id FROM indicators WHERE code = 'cempre_salaried_personnel') BEGIN SELECT RAISE(ABORT, 'Publication failed'); END");
        $this->artisan('indicators:sync-cempre')->assertFailed();
        $this->assertDatabaseCount('indicators', 0);
        $this->assertDatabaseCount('municipality_indicator_values', 0);
    }
}
