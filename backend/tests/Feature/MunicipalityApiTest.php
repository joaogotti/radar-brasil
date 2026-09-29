<?php

namespace Tests\Feature;

use App\Models\Municipality;
use App\Models\State;
use Database\Seeders\StateSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MunicipalityApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_filters_by_state_ibge_code_and_sorts_names_with_accents(): void
    {
        $this->seed(StateSeeder::class);
        $state = State::query()->where('ibge_code', 32)->firstOrFail();
        $this->assertNotSame(32, $state->id);

        foreach (['Vitória', 'Águia Branca', 'Afonso Cláudio', 'Venda Nova do Imigrante'] as $index => $name) {
            $state->municipalities()->create(['ibge_code' => 3200000 + $index, 'name' => $name]);
        }

        State::query()->where('ibge_code', 35)->firstOrFail()
            ->municipalities()->create(['ibge_code' => 3550308, 'name' => 'São Paulo']);

        $data = $this->getJson('/api/states/32/municipalities')
            ->assertOk()
            ->assertJsonCount(4)
            ->assertJsonStructure(['*' => ['id', 'state_id', 'ibge_code', 'name']])
            ->json();

        $this->assertSame(['Afonso Cláudio', 'Águia Branca', 'Venda Nova do Imigrante', 'Vitória'], array_column($data, 'name'));
        foreach ($data as $municipality) {
            $this->assertSame($state->id, $municipality['state_id']);
            $this->assertIsInt($municipality['ibge_code']);
            $this->assertSame(['id', 'state_id', 'ibge_code', 'name'], array_keys($municipality));
        }
    }

    public function test_an_unknown_state_returns_json_not_found_without_an_accept_header(): void
    {
        $this->get('/api/states/99/municipalities')
            ->assertNotFound()
            ->assertHeader('Content-Type', 'application/json')
            ->assertJsonStructure(['message']);
    }

    public function test_invalid_state_codes_return_not_found(): void
    {
        foreach (['ES', 'invalid', '214748364800'] as $code) {
            $this->getJson("/api/states/{$code}/municipalities")->assertNotFound();
        }
    }

    public function test_a_state_without_municipalities_returns_an_empty_array(): void
    {
        $this->seed(StateSeeder::class);
        $this->getJson('/api/states/32/municipalities')->assertOk()->assertExactJson([]);
    }

    public function test_eloquent_relationships_work_in_both_directions(): void
    {
        $this->seed(StateSeeder::class);
        $state = State::query()->where('ibge_code', 32)->firstOrFail();
        $municipality = $state->municipalities()->create(['ibge_code' => 3205309, 'name' => 'Vitória']);

        $this->assertTrue($municipality->state->is($state));
        $this->assertTrue($state->municipalities->sole()->is($municipality));
    }

    public function test_the_database_rejects_duplicate_municipality_codes(): void
    {
        $this->seed(StateSeeder::class);
        $state = State::query()->firstOrFail();
        $state->municipalities()->create(['ibge_code' => 3205309, 'name' => 'Vitória']);
        $this->expectException(QueryException::class);
        $state->municipalities()->create(['ibge_code' => 3205309, 'name' => 'Duplicate']);
    }

    public function test_the_database_rejects_a_municipality_without_a_valid_state(): void
    {
        $this->expectException(QueryException::class);
        Municipality::create(['state_id' => 999, 'ibge_code' => 3205309, 'name' => 'Vitória']);
    }
}
