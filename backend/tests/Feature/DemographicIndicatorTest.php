<?php

namespace Tests\Feature;

use App\Models\Indicator;
use App\Models\MunicipalityIndicatorValue;
use App\Models\State;
use App\Services\DemographicIndicatorSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DemographicIndicatorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        $state = State::create(['ibge_code' => 32, 'name' => 'Espírito Santo', 'abbreviation' => 'ES']);
        foreach ([3205309 => 'Vitória', 3200102 => 'Afonso Cláudio', 3550308 => 'São Paulo', 5101837 => 'Boa Esperança do Norte'] as $code => $name) {
            $state->municipalities()->create(['ibge_code' => $code, 'name' => $name]);
        }
    }

    private function fake(?callable $change = null): void
    {
        Http::swap(new Factory);
        Http::preventStrayRequests();
        $fixture = json_decode(file_get_contents(base_path('tests/Fixtures/demographic-indicators.json')), true, flags: JSON_THROW_ON_ERROR);
        Http::fake(function (Request $request) use ($fixture, $change) {
            preg_match('~/agregados/(\d+)/~', $request->url(), $match);
            $payload = $fixture[$match[1]];
            preg_match('~/variaveis/([^?]+)~', urldecode($request->url()), $selection);
            $payload = array_values(array_filter($payload, fn ($variable) => in_array($variable['id'], explode('|', $selection[1]), true)));
            if ($request['localidades'] !== 'N6[all]') {
                preg_match('/N6\[(\d+)\]/', $request['localidades'], $locality);
                foreach ($payload as &$variable) {
                    $variable['resultados'][0]['series'] = array_values(array_filter($variable['resultados'][0]['series'], fn ($series) => $series['localidade']['id'] === $locality[1]));
                }
            }

            return $change ? $change($payload, $request) : Http::response($payload);
        });
    }

    public function test_catalog_values_omissions_rerun_and_existing_contract(): void
    {
        $this->fake();
        $service = app(DemographicIndicatorSyncService::class);
        $result = $service->sync();
        $this->assertSame(5, $result['requests']);
        $this->assertSame(0, $result['retries']);
        $this->assertDatabaseCount('indicators', 7);
        $this->assertDatabaseCount('municipality_indicator_values', 28);
        foreach (config('demography.indicators') as $code => $definition) {
            $indicator = Indicator::where('code', $code)->firstOrFail();
            $this->assertSame($definition['aggregate'], $indicator->source_aggregate_id);
            $this->assertSame($definition['variable'], $indicator->source_variable_id);
            $this->assertSame($definition['unit'], $indicator->unit);
            $this->assertSame($definition['universe'], $indicator->source_universe);
            $this->assertSame($definition['definition'], $indicator->source_definition);
            $this->assertSame(count($definition['filters']), count($indicator->source_filters['classifications']));
            $this->assertSame(1, $result['counts'][$code]['omitted']);
        }
        $response = $this->getJson('/api/municipalities/3205309/indicators')->assertOk()->assertJsonCount(7, 'indicators');
        $this->assertSame('85.200000', collect($response->json('indicators'))->firstWhere('code', 'aging_index_65_plus')['value']);
        $this->assertSame(['code', 'name', 'value', 'unit', 'reference_year'], array_keys($response->json('indicators.0')));
        $this->getJson('/api/municipalities/5101837/indicators')->assertOk()->assertJsonCount(0, 'indicators');
        $before = MunicipalityIndicatorValue::orderBy('id')->get(['id', 'created_at', 'value', 'status', 'source_value'])->toArray();
        $this->travel(1)->hours();
        $service->sync();
        $this->assertSame($before, MunicipalityIndicatorValue::orderBy('id')->get(['id', 'created_at', 'value', 'status', 'source_value'])->toArray());
    }

    public function test_explicit_absence_replaces_number_and_can_recover(): void
    {
        $this->fake();
        app(DemographicIndicatorSyncService::class)->sync();
        $original = MunicipalityIndicatorValue::whereHas('indicator', fn ($q) => $q->where('code', 'median_age'))->whereHas('municipality', fn ($q) => $q->where('ibge_code', 3205309))->firstOrFail();
        $this->fake(function ($payload) {
            if ($payload[0]['id'] === '10613') {
                $payload[0]['resultados'][0]['series'][0]['serie']['2022'] = '...';
            }

            return Http::response($payload);
        });
        app(DemographicIndicatorSyncService::class)->sync();
        $this->assertNull($original->fresh()->value);
        $this->assertSame('unavailable', $original->fresh()->status);
        $this->assertSame('...', $original->fresh()->source_value);
        $this->fake();
        app(DemographicIndicatorSyncService::class)->sync();
        $this->assertSame('available', $original->fresh()->status);
        $this->assertTrue($original->created_at->equalTo($original->fresh()->created_at));
    }

    public function test_invalid_group_does_not_write_part_of_the_group(): void
    {
        $this->fake(function ($payload) {
            $payload[0]['unidade'] = 'invalid';

            return Http::response($payload);
        });
        $this->artisan('indicators:sync-demography')->assertFailed();
        $this->assertDatabaseCount('indicators', 0);
        $this->assertDatabaseCount('municipality_indicator_values', 0);
    }

    public function test_unresolved_omission_preserves_previous_number(): void
    {
        $this->fake();
        app(DemographicIndicatorSyncService::class)->sync();
        $before = MunicipalityIndicatorValue::orderBy('id')->get()->toArray();
        $this->fake(function ($payload, $request) {
            if ($request['localidades'] !== 'N6[all]') {
                return Http::response([], 503);
            }
            foreach ($payload as &$variable) {
                array_shift($variable['resultados'][0]['series']);
            }

            return Http::response($payload);
        });
        $this->artisan('indicators:sync-demography')->assertFailed();
        $this->assertSame($before, MunicipalityIndicatorValue::orderBy('id')->get()->toArray());
    }

    public function test_transient_errors_retry_but_not_forever(): void
    {
        $attempts = 0;
        $this->fake(function ($payload) use (&$attempts) {
            return ++$attempts < 3 ? Http::response([], 503) : Http::response($payload);
        });
        $result = app(DemographicIndicatorSyncService::class)->sync();
        $this->assertSame(2, $result['retries']);
        Http::swap(new Factory);
        Http::preventStrayRequests();
        Http::fake(['*' => Http::response([], 503)]);
        $this->artisan('indicators:sync-demography')->assertFailed();
        Http::assertSentCount(3);
        $this->assertDatabaseCount('municipality_indicator_values', 28);
    }

    public function test_directed_confirmation_resolves_a_previously_numeric_omission(): void
    {
        $this->fake();
        app(DemographicIndicatorSyncService::class)->sync();
        $original = MunicipalityIndicatorValue::whereHas('indicator', fn ($q) => $q->where('code', 'median_age'))
            ->whereHas('municipality', fn ($q) => $q->where('ibge_code', 3205309))->firstOrFail();
        $this->fake(function ($payload, $request) {
            if ($payload[0]['id'] === '10613') {
                if ($request['localidades'] === 'N6[all]') {
                    array_shift($payload[0]['resultados'][0]['series']);
                } else {
                    $payload[0]['resultados'][0]['series'][0]['serie']['2022'] = '...';
                }
            }

            return Http::response($payload);
        });
        $result = app(DemographicIndicatorSyncService::class)->sync();
        $this->assertSame(6, $result['requests']);
        $this->assertNull($original->fresh()->value);
        $this->assertSame('unavailable', $original->fresh()->status);
        $this->assertSame('...', $original->fresh()->source_value);
        $this->assertTrue($original->created_at->equalTo($original->fresh()->created_at));
        Http::assertSent(fn (Request $request) => $request['localidades'] === 'N6[3205309]');
    }

    public function test_filters_and_all_reference_values_match_the_audited_cuts(): void
    {
        $this->fake();
        app(DemographicIndicatorSyncService::class)->sync();
        $expected = [
            'median_age' => ['38.000000', []],
            'aging_index_65_plus' => ['85.200000', []],
            'sex_ratio' => ['86.180000', []],
            'literacy_rate_15_plus' => ['97.760000', [['id' => 2, 'categories' => [6794]], ['id' => 86, 'categories' => [95251]], ['id' => 287, 'categories' => [100362]]]],
            'households_main_water_network_pct' => ['99.520000', [['id' => 1821, 'categories' => [72144]]]],
            'households_sewer_stormwater_or_connected_septic_pct' => ['99.670000', [['id' => 11558, 'categories' => [46290]]]],
            'households_collected_garbage_pct' => ['99.740000', [['id' => 67, 'categories' => [2520]]]],
        ];
        $response = collect($this->getJson('/api/municipalities/3205309/indicators')->assertOk()->json('indicators'))->keyBy('code');
        foreach ($expected as $code => [$number, $filters]) {
            $this->assertSame($number, $response[$code]['value']);
            $this->assertSame(['classifications' => $filters], Indicator::where('code', $code)->firstOrFail()->source_filters);
        }
        Http::assertSent(fn (Request $request) => str_contains($request->url(), '/6805/') && $request['classificacao'] === '11558[46290]');
    }
}
