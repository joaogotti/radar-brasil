<?php

namespace Tests\Feature;

use App\Models\Municipality;
use App\Models\MunicipalityAgeSexValue;
use App\Models\State;
use App\Services\MunicipalityAgeDistributionSyncService;
use App\Support\CensusSyncLock;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MunicipalityAgeDistributionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        $state = State::create(['ibge_code' => 32, 'name' => 'Espírito Santo', 'abbreviation' => 'ES']);
        foreach ([3205309 => 'Vitória', 3550308 => 'São Paulo', 5101837 => 'Boa Esperança do Norte'] as $code => $name) {
            $state->municipalities()->create(['ibge_code' => $code, 'name' => $name]);
        }
    }

    private function fake(?callable $change = null): void
    {
        Http::swap(new Factory);
        Http::preventStrayRequests();
        $fixture = json_decode(file_get_contents(base_path('tests/Fixtures/municipality-age-distribution.json')), true, flags: JSON_THROW_ON_ERROR);
        Http::fake(function (Request $request) use ($fixture, $change) {
            preg_match('~/variaveis/(\d+)~', $request->url(), $match);
            $variable = collect($fixture)->firstWhere('id', $match[1]);
            preg_match_all('/(\d+)\[([\d,]+)\]/', $request['classificacao'], $filters, PREG_SET_ORDER);
            $allowed = [];
            foreach ($filters as $filter) {
                $allowed[$filter[1]] = explode(',', $filter[2]);
            }
            $variable['resultados'] = array_values(array_filter($variable['resultados'], function ($result) use ($allowed) {
                foreach ($result['classificacoes'] as $classification) {
                    if (! in_array((string) array_key_first($classification['categoria']), $allowed[$classification['id']], true)) {
                        return false;
                    }
                }

                return true;
            }));
            $variable['resultados'] = array_reverse($variable['resultados']);
            foreach ($variable['resultados'] as &$result) {
                $result['classificacoes'] = array_reverse($result['classificacoes']);
                $result['series'] = array_reverse($result['series']);
            }

            return $change ? $change([$variable]) : Http::response([$variable]);
        });
    }

    public function test_complete_distribution_pairing_official_rounding_and_rerun(): void
    {
        $this->fake();
        $service = app(MunicipalityAgeDistributionSyncService::class);
        $result = $service->sync();
        $this->assertSame(28, $result['requests']);
        $this->assertSame(126, $result['rows']);
        $this->assertDatabaseCount('age_groups', 21);
        $this->assertSame(42, $result['counts']['population']['omitted']);
        $response = $this->getJson('/api/municipalities/3205309/age-distribution')->assertOk()->assertJsonPath('availability', 'complete')->assertJsonCount(42, 'data');
        $data = $response->json('data');
        $this->assertSame(322869, array_sum(array_column($data, 'population')));
        $hundredths = array_sum(array_map(fn ($row) => (int) str_replace('.', '', substr($row['percentage'], 0, -4)), $data));
        $this->assertSame(10001, $hundredths);
        $this->assertSame(4, $data[0]['sex_code']);
        $this->assertSame(5, $data[1]['sex_code']);
        $this->assertSame(93070, $data[0]['age_group']['ibge_code']);
        $this->assertSame(8801, $data[0]['population']);
        $this->assertSame('2.730000', $data[0]['percentage']);
        $this->assertSame(6653, $data[41]['age_group']['ibge_code']);
        $this->assertNull($data[41]['age_group']['max_age']);
        $this->assertArrayNotHasKey('source_value', $data[0]);
        $sp = $this->getJson('/api/municipalities/3550308/age-distribution')->assertOk()->json('data');
        $this->assertSame(337, $sp[40]['population']);
        $this->assertSame('0.000000', $sp[40]['percentage']);
        $this->getJson('/api/municipalities/5101837/age-distribution')->assertJsonPath('availability', 'unavailable')->assertJsonCount(42, 'data')->assertJsonPath('data.0.population_status', 'omitted')->assertJsonPath('data.0.population', null);
        $before = MunicipalityAgeSexValue::orderBy('id')->get(['id', 'created_at', 'population', 'percentage', 'population_status', 'percentage_status'])->toArray();
        $this->travel(1)->hours();
        $service->sync();
        $this->assertSame($before, MunicipalityAgeSexValue::orderBy('id')->get(['id', 'created_at', 'population', 'percentage', 'population_status', 'percentage_status'])->toArray());
        $row = MunicipalityAgeSexValue::firstOrFail();
        $this->assertTrue($row->municipality->ageSexValues->contains($row));
        $this->assertTrue($row->ageGroup->ageSexValues->contains($row));
    }

    public function test_not_loaded_not_found_and_partial_without_metadata_leaks(): void
    {
        $this->getJson('/api/municipalities/3205309/age-distribution')->assertOk()->assertJsonPath('availability', 'not_loaded')->assertJsonCount(0, 'data');
        foreach (['invalid', '9999999', '999999999999999999'] as $code) {
            $this->getJson('/api/municipalities/'.$code.'/age-distribution')->assertNotFound()->assertExactJson(['message' => 'Município não encontrado.']);
        }
        $this->fake();
        app(MunicipalityAgeDistributionSyncService::class)->sync();
        MunicipalityAgeSexValue::where('municipality_id', Municipality::where('ibge_code', 3205309)->value('id'))->firstOrFail()
            ->update(['percentage' => null, 'percentage_status' => 'suppressed', 'percentage_source_value' => 'X']);
        $response = $this->getJson('/api/municipalities/3205309/age-distribution')->assertJsonPath('availability', 'partial')->assertJsonCount(42, 'data');
        $this->assertStringNotContainsString('last_synced_at', $response->getContent());
        $this->assertStringNotContainsString('source_value', $response->getContent());
    }

    public function test_invalid_last_batch_cannot_publish_any_changes(): void
    {
        $this->fake();
        app(MunicipalityAgeDistributionSyncService::class)->sync();
        $before = MunicipalityAgeSexValue::orderBy('id')->get()->toArray();
        $calls = 0;
        $this->fake(function ($payload) use (&$calls) {
            if (++$calls === 28) {
                array_pop($payload[0]['resultados']);
            }

            return Http::response($payload);
        });
        $this->artisan('municipalities:sync-age-distribution')->assertFailed();
        $this->assertSame($before, MunicipalityAgeSexValue::orderBy('id')->get()->toArray());
    }

    public function test_markers_are_independent_for_each_measure(): void
    {
        $this->fake(function ($payload) {
            foreach ($payload[0]['resultados'] as &$result) {
                foreach ($result['series'] as &$series) {
                    $series['serie']['2022'] = $payload[0]['id'] === '93' ? '-' : '...';
                }
            }

            return Http::response($payload);
        });
        app(MunicipalityAgeDistributionSyncService::class)->sync();
        $this->getJson('/api/municipalities/3205309/age-distribution')->assertJsonPath('availability', 'partial')
            ->assertJsonPath('data.0.population', 0)->assertJsonPath('data.0.percentage', null)->assertJsonPath('data.0.percentage_status', 'unavailable');
    }

    public function test_unique_constraint_rejects_duplicate_cell(): void
    {
        $this->fake();
        app(MunicipalityAgeDistributionSyncService::class)->sync();
        $this->expectException(QueryException::class);
        MunicipalityAgeSexValue::firstOrFail()->replicate()->save();
    }

    public function test_lock_prevents_concurrent_sync_without_http(): void
    {
        app(CensusSyncLock::class)->run('age-distribution', function (): void {
            $this->artisan('municipalities:sync-age-distribution')->assertFailed();
        });
        Http::assertNothingSent();
        $this->assertDatabaseCount('age_groups', 0);
    }

    public function test_revision_changes_one_measure_to_absence_and_back_preserving_identity(): void
    {
        $this->fake();
        app(MunicipalityAgeDistributionSyncService::class)->sync();
        $before = MunicipalityAgeSexValue::orderBy('id')->get(['id', 'created_at', 'population', 'percentage'])->toArray();
        $this->fake(function ($payload) {
            if ($payload[0]['id'] === '1000093') {
                foreach ($payload[0]['resultados'] as &$result) {
                    foreach ($result['series'] as &$series) {
                        $series['serie']['2022'] = 'X';
                    }
                }
            }

            return Http::response($payload);
        });
        app(MunicipalityAgeDistributionSyncService::class)->sync();
        $this->assertSame(84, MunicipalityAgeSexValue::where('percentage_status', 'suppressed')->whereNull('percentage')->count());
        $this->assertSame(84, MunicipalityAgeSexValue::where('population_status', 'available')->count());
        $this->fake();
        app(MunicipalityAgeDistributionSyncService::class)->sync();
        $this->assertSame($before, MunicipalityAgeSexValue::orderBy('id')->get(['id', 'created_at', 'population', 'percentage'])->toArray());
    }

    public function test_missing_locality_in_one_cell_rejects_the_whole_publication(): void
    {
        $this->fake(function ($payload) {
            array_pop($payload[0]['resultados'][0]['series']);

            return Http::response($payload);
        });
        $this->artisan('municipalities:sync-age-distribution')->assertFailed();
        $this->assertDatabaseCount('municipality_age_sex_values', 0);
        $this->assertDatabaseCount('age_groups', 0);
    }
}
