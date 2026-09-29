<?php

namespace Tests\Feature;

use App\Models\State;
use Database\Seeders\StateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MunicipalitySearchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(StateSeeder::class);
    }

    private function createMunicipality(string $name, int $code, int $state = 32): void
    {
        State::query()->where('ibge_code', $state)->firstOrFail()
            ->municipalities()->create(['name' => $name, 'ibge_code' => $code]);
    }

    public function test_default_pagination_and_response_contract(): void
    {
        for ($i = 24; $i >= 0; $i--) {
            $this->createMunicipality(sprintf('Municipio %02d', $i), 3200000 + $i);
        }

        $response = $this->getJson('/api/municipalities')->assertOk()
            ->assertJsonCount(20, 'data')->assertJsonPath('current_page', 1)
            ->assertJsonPath('last_page', 2)->assertJsonPath('per_page', 20)
            ->assertJsonPath('total', 25)->assertJsonPath('data.0.name', 'Municipio 00')
            ->assertJsonPath('data.0.state', ['ibge_code' => 32, 'name' => 'Espírito Santo', 'abbreviation' => 'ES']);
        $this->assertSame(['id', 'ibge_code', 'name', 'state'], array_keys($response->json('data.0')));
        $this->getJson('/api/municipalities?page=2')->assertOk()->assertJsonCount(5, 'data')
            ->assertJsonPath('data.0.name', 'Municipio 20')->assertJsonPath('current_page', 2);
        $this->getJson('/api/municipalities?per_page=7&page=2')->assertOk()
            ->assertJsonCount(7, 'data')->assertJsonPath('per_page', 7)->assertJsonPath('last_page', 4)
            ->assertJsonPath('data.0.name', 'Municipio 07');
        $this->getJson('/api/municipalities?per_page=100')->assertOk()->assertJsonPath('per_page', 100);
    }

    public function test_search_state_and_combined_filters(): void
    {
        $this->createMunicipality('Santa Teresa', 3204609);
        $this->createMunicipality('Santa Maria', 3204559);
        $this->createMunicipality('Santa Outra', 3500000, 35);
        $this->createMunicipality('Colatina', 3201506);

        $this->getJson('/api/municipalities?search=%20SANTA%20')->assertOk()->assertJsonPath('total', 3);
        $this->getJson('/api/municipalities?search=Teresa')->assertOk()->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.name', 'Santa Teresa');
        $this->getJson('/api/municipalities?state=32')->assertOk()->assertJsonPath('total', 3);
        $response = $this->getJson('/api/municipalities?search=santa&state=32&per_page=1')->assertOk()
            ->assertJsonPath('total', 2)->assertJsonPath('data.0.name', 'Santa Maria')
            ->assertJsonPath('data.0.state.ibge_code', 32);
        parse_str(parse_url($response->json('next_page_url'), PHP_URL_QUERY), $parameters);
        $this->assertSame(['search' => 'santa', 'state' => '32', 'per_page' => '1', 'page' => '2'], $parameters);
        $this->getJson('/api/municipalities?search=%20%20')->assertOk()->assertJsonPath('total', 4);
    }

    public function test_empty_results_and_unknown_state(): void
    {
        $this->getJson('/api/municipalities?search=nonexistent')->assertOk()->assertJsonPath('data', [])
            ->assertJsonPath('total', 0)->assertJsonPath('last_page', 1)->assertJsonPath('current_page', 1);
        $this->getJson('/api/municipalities?state=32&page=2')->assertOk()->assertJsonPath('data', []);
        $this->get('/api/municipalities?state=99')->assertNotFound()
            ->assertExactJson(['message' => 'UF não encontrada.']);
    }

    public function test_invalid_parameters_return_validation_errors(): void
    {
        foreach ([
            'search' => [str_repeat('a', 101), ['invalid']],
            'state' => ['ES', '3', '320', '', ['32'], "32'"],
            'page' => [0, -1, '1.5', 'abc', '', ['1'], '999999999999999999999'],
            'per_page' => [0, -1, 101, '1.5', 'abc', '', ['20']],
        ] as $parameter => $values) {
            foreach ($values as $value) {
                $this->getJson('/api/municipalities?'.http_build_query([$parameter => $value]))
                    ->assertUnprocessable()->assertJsonValidationErrors($parameter);
            }
        }
    }

    public function test_wildcards_quotes_and_escape_character_are_literal(): void
    {
        foreach (['100% Cidade', 'Cidade_A', "D'Oeste", 'Cidade "B"', 'Cidade!C', 'Cidade\\D', 'Outra Cidade'] as $i => $name) {
            $this->createMunicipality($name, 3200000 + $i);
        }

        foreach (['%', '_', "'", '"', '!', '\\'] as $character) {
            $this->getJson('/api/municipalities?'.http_build_query(['search' => $character]))
                ->assertOk()->assertJsonPath('total', 1);
        }
        $this->getJson('/api/municipalities?'.http_build_query(['search' => "' OR 1=1 --"]))
            ->assertOk()->assertJsonPath('total', 0);
    }

    public function test_pagination_has_stable_ties_and_no_n_plus_one(): void
    {
        foreach ([3200003, 3200001, 3200002] as $code) {
            $this->createMunicipality('Mesmo Nome', $code);
        }

        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->getJson('/api/municipalities')->assertOk()->assertJsonPath('data.0.ibge_code', 3200001);
        $queries = DB::getQueryLog();
        DB::disableQueryLog();
        $selects = array_filter($queries, fn (array $query): bool => str_starts_with(strtolower($query['query']), 'select'));
        $this->assertCount(3, $selects);
        $this->getJson('/api/municipalities?per_page=1&page=2')->assertOk()->assertJsonPath('data.0.ibge_code', 3200002);
    }
}
