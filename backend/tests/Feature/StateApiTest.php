<?php

namespace Tests\Feature;

use App\Models\State;
use Database\Seeders\StateSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StateApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_all_official_states_in_alphabetical_order(): void
    {
        $this->seed(StateSeeder::class);

        $states = $this->getJson('/api/states')
            ->assertOk()
            ->assertJsonCount(27)
            ->assertJsonStructure(['*' => ['id', 'ibge_code', 'name', 'abbreviation']])
            ->json();

        $this->assertSame([
            'AC' => 12, 'AL' => 27, 'AP' => 16, 'AM' => 13, 'BA' => 29,
            'CE' => 23, 'DF' => 53, 'ES' => 32, 'GO' => 52, 'MA' => 21,
            'MT' => 51, 'MS' => 50, 'MG' => 31, 'PA' => 15, 'PB' => 25,
            'PR' => 41, 'PE' => 26, 'PI' => 22, 'RJ' => 33, 'RN' => 24,
            'RS' => 43, 'RO' => 11, 'RR' => 14, 'SC' => 42, 'SP' => 35,
            'SE' => 28, 'TO' => 17,
        ], array_column($states, 'ibge_code', 'abbreviation'));
        $this->assertSame([
            'Acre', 'Alagoas', 'Amapá', 'Amazonas', 'Bahia', 'Ceará',
            'Distrito Federal', 'Espírito Santo', 'Goiás', 'Maranhão',
            'Mato Grosso', 'Mato Grosso do Sul', 'Minas Gerais', 'Pará',
            'Paraíba', 'Paraná', 'Pernambuco', 'Piauí', 'Rio de Janeiro',
            'Rio Grande do Norte', 'Rio Grande do Sul', 'Rondônia',
            'Roraima', 'Santa Catarina', 'São Paulo', 'Sergipe', 'Tocantins',
        ], array_column($states, 'name'));

        foreach ($states as $state) {
            $this->assertIsInt($state['id']);
            $this->assertSame(['id', 'ibge_code', 'name', 'abbreviation'], array_keys($state));
        }
    }

    public function test_seeding_twice_preserves_ids_and_does_not_duplicate_states(): void
    {
        $this->seed(StateSeeder::class);
        $ids = State::query()->orderBy('ibge_code')->pluck('id', 'ibge_code')->all();

        State::query()->where('ibge_code', 32)->update(['name' => 'Outdated name']);
        $this->seed(StateSeeder::class);

        $this->assertDatabaseCount('states', 27);
        $this->assertSame($ids, State::query()->orderBy('ibge_code')->pluck('id', 'ibge_code')->all());
        $this->assertDatabaseHas('states', ['ibge_code' => 32, 'name' => 'Espírito Santo']);
    }

    public function test_it_returns_an_empty_array_when_no_states_are_loaded(): void
    {
        $this->getJson('/api/states')->assertOk()->assertExactJson([]);
    }

    public function test_the_database_rejects_duplicate_ibge_codes(): void
    {
        $this->seed(StateSeeder::class);
        $this->expectException(QueryException::class);

        State::create(['ibge_code' => 32, 'name' => 'Duplicate', 'abbreviation' => 'XX']);
    }

    public function test_the_database_rejects_duplicate_abbreviations(): void
    {
        $this->seed(StateSeeder::class);
        $this->expectException(QueryException::class);

        State::create(['ibge_code' => 99, 'name' => 'Duplicate', 'abbreviation' => 'ES']);
    }
}
