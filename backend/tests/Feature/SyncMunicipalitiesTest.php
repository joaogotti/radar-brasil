<?php

namespace Tests\Feature;

use App\Models\Municipality;
use App\Models\State;
use Database\Seeders\StateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SyncMunicipalitiesTest extends TestCase
{
    use RefreshDatabase;

    private const URL = 'https://servicodados.ibge.gov.br/api/v1/localidades/municipios';

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
    }

    public function test_sync_is_repeatable_and_updates_names_preserving_ids_and_creation_dates(): void
    {
        $this->seed(StateSeeder::class);
        Http::fake([self::URL => Http::response([
            $this->municipality(3205309, 'Vitória', 32),
            ['id' => 3550308, 'nome' => 'São Paulo', 'microrregiao' => ['mesorregiao' => ['UF' => ['id' => 35]]]],
        ])]);

        $this->artisan('municipalities:sync')->expectsOutput('Municípios sincronizados: 2.')->assertSuccessful();
        $ids = Municipality::query()->pluck('id', 'ibge_code')->all();
        $created = Municipality::query()->pluck('created_at', 'ibge_code')->all();
        Municipality::query()->where('ibge_code', 3205309)->update(['name' => 'Old name']);
        $this->travel(1)->hours();

        $this->artisan('municipalities:sync')->assertSuccessful();

        $this->assertDatabaseCount('municipalities', 2);
        $this->assertSame($ids, Municipality::query()->pluck('id', 'ibge_code')->all());
        $this->assertEquals($created, Municipality::query()->pluck('created_at', 'ibge_code')->all());
        $this->assertDatabaseHas('municipalities', [
            'ibge_code' => 3205309, 'name' => 'Vitória',
            'state_id' => State::query()->where('ibge_code', 32)->value('id'),
        ]);
        Http::assertSentCount(2);
    }

    public function test_unknown_state_aborts_before_writing_any_municipality(): void
    {
        $this->seed(StateSeeder::class);
        Http::fake([self::URL => Http::response([
            $this->municipality(3205309, 'Vitória', 32),
            $this->municipality(9900001, 'Invalid', 99),
        ])]);

        $this->artisan('municipalities:sync')->assertFailed();
        $this->assertDatabaseCount('municipalities', 0);
    }

    public function test_invalid_payloads_do_not_change_existing_data(): void
    {
        $this->seed(StateSeeder::class);
        $state = State::query()->where('ibge_code', 32)->firstOrFail();
        $state->municipalities()->create(['ibge_code' => 3205309, 'name' => 'Vitória']);
        $valid = $this->municipality(3205309, 'Changed', 32);

        foreach ([[], ['error' => 'Unavailable'], [$valid, $valid], [$valid, ['id' => 3200001]], [$this->municipality(3200001, 'Missing UF', null)]] as $payload) {
            Http::fake([self::URL => Http::response($payload)]);
            $this->artisan('municipalities:sync')->assertFailed();
            $this->assertDatabaseCount('municipalities', 1);
            $this->assertDatabaseHas('municipalities', ['ibge_code' => 3205309, 'name' => 'Vitória']);
        }
    }

    public function test_http_failure_does_not_write_municipalities(): void
    {
        Http::fake([self::URL => Http::response([], 503)]);
        $this->artisan('municipalities:sync')->assertFailed();
        $this->assertDatabaseCount('municipalities', 0);
        Http::assertSentCount(3);
    }

    private function municipality(int $code, string $name, ?int $stateCode): array
    {
        return [
            'id' => $code,
            'nome' => $name,
            'microrregiao' => null,
            'regiao-imediata' => ['regiao-intermediaria' => ['UF' => ['id' => $stateCode]]],
        ];
    }
}
