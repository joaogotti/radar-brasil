<?php

namespace Tests\Feature;

use App\Models\Indicator;
use App\Models\State;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class IndicatorSourceMigrationTest extends TestCase
{
    use DatabaseMigrations;

    public function test_migration_preserves_existing_catalog_and_values_and_can_roll_back(): void
    {
        $migration = require database_path('migrations/2026_09_29_000001_add_source_metadata_to_indicators.php');
        $migration->down();
        $this->assertFalse(Schema::hasColumn('indicators', 'source_type'));
        $indicator = Indicator::create([
            'code' => 'population_census', 'name' => 'População residente no Censo', 'unit' => 'Pessoas',
            'source' => 'IBGE - Censo Demográfico', 'source_aggregate_id' => 4714, 'source_variable_id' => 93,
        ]);
        $state = State::create(['ibge_code' => 32, 'name' => 'Espírito Santo', 'abbreviation' => 'ES']);
        $municipality = $state->municipalities()->create(['ibge_code' => 3205309, 'name' => 'Vitória']);
        $indicator->municipalityValues()->create(['municipality_id' => $municipality->id, 'value' => '322869', 'reference_year' => 2022]);
        $indicator->stateValues()->create(['state_id' => $state->id, 'value' => '3833712', 'reference_year' => 2022]);
        $tables = ['indicators', 'municipality_indicator_values', 'state_indicator_values'];
        $before = [];
        foreach ($tables as $table) {
            $before[$table] = DB::table($table)->orderBy('id')->get()->map(fn ($row) => (array) $row)->all();
        }

        $migration->up();
        $this->assertSame('ibge_aggregates', $indicator->fresh()->source_type);
        $this->getJson('/api/municipalities/3205309/indicators')->assertOk()->assertJsonPath('indicators.0.value', '322869.000000');
        foreach ($tables as $table) {
            $after = DB::table($table)->orderBy('id')->get(array_keys($before[$table][0]))->map(fn ($row) => (array) $row)->all();
            $this->assertSame($before[$table], $after);
        }

        $migration->down();
        $this->assertFalse(Schema::hasColumn('indicators', 'source_type'));
        foreach ($tables as $table) {
            $this->assertSame($before[$table], DB::table($table)->orderBy('id')->get()->map(fn ($row) => (array) $row)->all());
        }
        $migration->up();
    }
}
