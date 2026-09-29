<?php

namespace Tests\Feature;

use App\Models\Indicator;
use App\Models\Municipality;
use App\Models\MunicipalityIndicatorValue;
use App\Models\State;
use App\Models\StateIndicatorValue;
use App\Services\CensusIndicatorSyncService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CensusIndicatorTest extends TestCase
{
    use RefreshDatabase;

    private const URL = 'https://servicodados.ibge.gov.br/api/v3/agregados/4714/*';

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        $es = State::create(['ibge_code' => 32, 'name' => 'Espírito Santo', 'abbreviation' => 'ES']);
        $sp = State::create(['ibge_code' => 35, 'name' => 'São Paulo', 'abbreviation' => 'SP']);
        $es->municipalities()->create(['ibge_code' => 3205309, 'name' => 'Vitória']);
        $sp->municipalities()->create(['ibge_code' => 3550308, 'name' => 'São Paulo']);
    }

    private function payload(): array
    {
        return json_decode(file_get_contents(base_path('tests/Fixtures/census-indicators.json')), true, flags: JSON_THROW_ON_ERROR);
    }

    private function sync(): void
    {
        Http::fake([self::URL => Http::response($this->payload())]);
        $this->artisan('indicators:sync-census')->assertSuccessful();
    }

    public function test_api_returns_only_the_requested_municipality_with_decimal_strings_and_year(): void
    {
        $this->sync();
        $response = $this->getJson('/api/municipalities/3205309/indicators')->assertOk()->assertExactJson([
            'municipality' => ['ibge_code' => 3205309, 'name' => 'Vitória', 'state' => ['ibge_code' => 32, 'name' => 'Espírito Santo', 'abbreviation' => 'ES']],
            'indicators' => [
                ['code' => 'population_census', 'name' => 'População residente no Censo', 'value' => '322869.000000', 'unit' => 'Pessoas', 'reference_year' => 2022],
                ['code' => 'population_density', 'name' => 'Densidade demográfica no Censo', 'value' => '3324.330000', 'unit' => 'Habitante por quilômetro quadrado', 'reference_year' => 2022],
                ['code' => 'territorial_area', 'name' => 'Área da unidade territorial', 'value' => '97.123000', 'unit' => 'Quilômetros quadrados', 'reference_year' => 2022],
            ],
        ]);
        $this->assertNotContains('11451999.000000', array_column($response->json('indicators'), 'value'));
        Http::assertSentCount(1);
    }

    public function test_unknown_or_invalid_code_returns_clean_json_even_with_debug_enabled(): void
    {
        config(['app.debug' => true]);
        foreach (['9999999', '1', 'invalid', '999999999999999999999'] as $code) {
            $this->get("/api/municipalities/{$code}/indicators")
                ->assertNotFound()->assertExactJson(['message' => 'Município não encontrado.']);
        }
        Http::assertNothingSent();
    }

    public function test_municipality_without_values_returns_an_empty_array(): void
    {
        $this->getJson('/api/municipalities/3205309/indicators')->assertOk()->assertExactJson([
            'municipality' => ['ibge_code' => 3205309, 'name' => 'Vitória', 'state' => ['ibge_code' => 32, 'name' => 'Espírito Santo', 'abbreviation' => 'ES']], 'indicators' => [],
        ]);
        Http::assertNothingSent();
    }

    public function test_relationships_and_official_state_values(): void
    {
        $this->sync();
        $indicator = Indicator::where('code', 'population_census')->firstOrFail();
        $municipality = Municipality::where('ibge_code', 3205309)->firstOrFail();
        $state = $municipality->state;
        $municipalValue = $municipality->indicatorValues()->whereBelongsTo($indicator)->firstOrFail();
        $stateValue = $state->indicatorValues()->whereBelongsTo($indicator)->firstOrFail();
        $this->assertTrue($municipalValue->municipality->is($municipality));
        $this->assertTrue($municipalValue->indicator->is($indicator));
        $this->assertTrue($stateValue->state->is($state));
        $this->assertTrue($stateValue->indicator->is($indicator));
        $this->assertCount(2, $indicator->municipalityValues);
        $this->assertCount(2, $indicator->stateValues);
        $this->assertSame('3833712.000000', $stateValue->value);
        $this->assertSame(2022, $stateValue->reference_year);
    }

    public function test_resync_updates_values_preserves_ids_creation_dates_and_history(): void
    {
        $payload = $this->payload();
        $payload[0]['resultados'][0]['series'][0]['serie']['2022'] = '322870';
        $payload[0]['resultados'][0]['series'][2]['serie']['2022'] = '3833713';
        Http::fake([self::URL => Http::sequence()->push($this->payload())->push($payload)]);
        $this->artisan('indicators:sync-census')->assertSuccessful();
        $old = MunicipalityIndicatorValue::firstOrFail();
        $history = MunicipalityIndicatorValue::create([
            'indicator_id' => $old->indicator_id, 'municipality_id' => $old->municipality_id,
            'value' => '100', 'reference_year' => 2010,
        ]);
        $models = [Indicator::class, MunicipalityIndicatorValue::class, StateIndicatorValue::class];
        $identities = [];
        foreach ($models as $model) {
            $identities[$model] = $model::orderBy('id')->get(['id', 'created_at'])->toArray();
        }
        $this->travel(1)->hours();
        $this->artisan('indicators:sync-census')->assertSuccessful();
        foreach ($models as $model) {
            $this->assertSame($identities[$model], $model::orderBy('id')->get(['id', 'created_at'])->toArray());
        }
        $this->assertDatabaseCount('indicators', 3);
        $this->assertDatabaseCount('municipality_indicator_values', 7);
        $this->assertDatabaseCount('state_indicator_values', 6);
        $this->assertSame('322870.000000', $old->fresh()->value);
        $this->assertSame('100.000000', $history->fresh()->value);
        $this->assertDatabaseHas('state_indicator_values', ['value' => '3833713', 'reference_year' => 2022]);
        $this->getJson('/api/municipalities/3205309/indicators')->assertJsonPath('indicators.0.reference_year', 2022)
            ->assertJsonPath('indicators.1.reference_year', 2010);
    }

    public function test_registered_locality_absent_from_census_is_reported_without_inventing_values(): void
    {
        $municipality = State::firstOrFail()->municipalities()->create(['ibge_code' => 3209999, 'name' => 'New locality']);
        Http::fake([self::URL => Http::response($this->payload())]);
        $result = app(CensusIndicatorSyncService::class)->sync();
        $this->assertSame([3209999], $result['missing_municipalities']);
        $this->assertSame([], $result['missing_states']);
        $this->assertSame(['municipalities' => 2, 'states' => 2], $result['counts']['population_census']);
        $this->assertCount(0, $municipality->indicatorValues);
    }

    public function test_http_failure_leaves_existing_values_unchanged(): void
    {
        Http::fake([self::URL => Http::sequence()->push($this->payload())->push([], 503)->push([], 503)->push([], 503)]);
        $this->artisan('indicators:sync-census')->assertSuccessful();
        $this->artisan('indicators:sync-census')->assertFailed();
        $this->assertDatabaseCount('municipality_indicator_values', 6);
        $this->assertDatabaseCount('state_indicator_values', 6);
        $this->assertDatabaseHas('municipality_indicator_values', ['value' => '322869']);
    }

    #[DataProvider('invalidResponses')]
    public function test_invalid_external_data_is_rejected_before_any_writes(string $case): void
    {
        $payload = $this->payload();
        switch ($case) {
            case 'missing_variable': array_pop($payload);
                break;
            case 'duplicate_variable': $payload[1] = $payload[0];
                break;
            case 'unit': $payload[0]['unidade'] = 'Unknown';
                break;
            case 'classification': $payload[0]['resultados'][0]['classificacoes'] = [['id' => '1']];
                break;
            case 'unknown_locality': $payload[0]['resultados'][0]['series'][0]['localidade']['id'] = '9999999';
                break;
            case 'duplicate_locality': $payload[0]['resultados'][0]['series'][] = $payload[0]['resultados'][0]['series'][0];
                break;
            case 'period': $payload[0]['resultados'][0]['series'][0]['serie'] = ['2023' => '123'];
                break;
            case 'unavailable': $payload[2]['resultados'][0]['series'][0]['serie']['2022'] = '...';
                break;
            case 'precision': $payload[2]['resultados'][0]['series'][0]['serie']['2022'] = '1.1234567';
                break;
            case 'fractional_population': $payload[0]['resultados'][0]['series'][0]['serie']['2022'] = '123.5';
                break;
            case 'coverage': array_shift($payload[2]['resultados'][0]['series']);
                break;
            case 'empty': $payload = [];
                break;
        }
        Http::fake([self::URL => Http::response($payload)]);
        $this->artisan('indicators:sync-census')->assertFailed();
        $this->assertDatabaseCount('indicators', 0);
        $this->assertDatabaseCount('municipality_indicator_values', 0);
        $this->assertDatabaseCount('state_indicator_values', 0);
    }

    public static function invalidResponses(): array
    {
        return array_map(fn (string $case): array => [$case], [
            'missing_variable', 'duplicate_variable', 'unit', 'classification', 'unknown_locality',
            'duplicate_locality', 'period', 'unavailable', 'precision', 'fractional_population', 'coverage', 'empty',
        ]);
    }

    #[DataProvider('valueModels')]
    public function test_database_rejects_duplicate_locality_indicator_year(string $model): void
    {
        $this->sync();
        $value = $model::firstOrFail();
        $this->expectException(QueryException::class);
        $value->replicate()->save();
    }

    #[DataProvider('valueModels')]
    public function test_database_rejects_unknown_indicators(string $model): void
    {
        $this->sync();
        $value = $model::firstOrFail();
        $this->expectException(QueryException::class);
        $value->update(['indicator_id' => 999]);
    }

    public static function valueModels(): array
    {
        return [[MunicipalityIndicatorValue::class], [StateIndicatorValue::class]];
    }
}
