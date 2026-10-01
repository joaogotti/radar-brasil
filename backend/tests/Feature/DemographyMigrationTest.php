<?php

namespace Tests\Feature;

use App\Models\AgeGroup;
use App\Models\Indicator;
use App\Models\MunicipalityAgeSexValue;
use App\Models\MunicipalityIndicatorValue;
use App\Models\State;
use Database\Seeders\AgeGroupSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class DemographyMigrationTest extends TestCase
{
    use RefreshDatabase;

    private function numericRow(): MunicipalityIndicatorValue
    {
        $state = State::create(['ibge_code' => 32, 'name' => 'Espírito Santo', 'abbreviation' => 'ES']);
        $municipality = $state->municipalities()->create(['ibge_code' => 3205309, 'name' => 'Vitória']);
        $indicator = Indicator::create(['code' => 'fixture', 'name' => 'Fixture', 'unit' => 'Mil Reais', 'source' => 'IBGE', 'source_type' => 'ibge_aggregates', 'source_aggregate_id' => 5938, 'source_variable_id' => 37]);

        return $municipality->indicatorValues()->create(['indicator_id' => $indicator->id, 'reference_year' => 2022, 'value' => '-19046']);
    }

    public function test_status_migration_preserves_legacy_values_identity_and_timestamps(): void
    {
        $migration = require database_path('migrations/2026_10_01_000001_add_observation_status_to_municipality_indicator_values.php');
        $migration->down();
        $row = $this->numericRow();
        $before = (array) DB::table('municipality_indicator_values')->first();
        $migration->up();
        $this->assertSame($before, (array) DB::table('municipality_indicator_values')->first(array_keys($before)));
        $this->assertSame('available', $row->fresh()->status);
        $this->assertNull($row->fresh()->source_value);
        $this->assertNull($row->fresh()->last_synced_at);
        $migration->down();
        $this->assertSame($before, (array) DB::table('municipality_indicator_values')->first());
        $migration->up();
    }

    public function test_rollback_refuses_to_discard_absence(): void
    {
        $row = $this->numericRow();
        $row->update(['value' => null, 'status' => 'unavailable', 'source_value' => '...']);
        $migration = require database_path('migrations/2026_10_01_000001_add_observation_status_to_municipality_indicator_values.php');
        try {
            $migration->down();
            $this->fail('Rollback must fail.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('unavailable observations', $e->getMessage());
        }
        $this->assertTrue(Schema::hasColumn('municipality_indicator_values', 'status'));
        $this->assertNull($row->fresh()->value);
    }

    public function test_constraints_protect_status_values_ranges_foreign_keys_and_uniqueness(): void
    {
        $row = $this->numericRow();
        foreach ([['value' => null], ['status' => 'suppressed'], ['status' => 'unknown']] as $invalid) {
            try {
                DB::transaction(fn () => $row->update($invalid));
                $this->fail('Invalid observation accepted.');
            } catch (QueryException) {
                $row->refresh();
            }
        }
        $this->seed(AgeGroupSeeder::class);
        $this->seed(AgeGroupSeeder::class);
        $this->assertDatabaseCount('age_groups', 21);
        $base = ['municipality_id' => $row->municipality_id, 'reference_year' => 2022, 'sex_code' => 4, 'age_group_id' => AgeGroup::firstOrFail()->id,
            'population' => 1, 'percentage' => '0.00', 'population_status' => 'available', 'percentage_status' => 'available', 'last_synced_at' => now()];
        foreach ([['population' => -1], ['percentage' => '100.1'], ['sex_code' => 6], ['reference_year' => 0], ['population_status' => 'omitted'], ['percentage' => null], ['municipality_id' => 999999], ['age_group_id' => 999999]] as $invalid) {
            try {
                DB::transaction(fn () => MunicipalityAgeSexValue::create([...$base, ...$invalid]));
                $this->fail('Invalid age/sex observation accepted.');
            } catch (QueryException) {
                $this->assertDatabaseCount('municipality_age_sex_values', 0);
            }
        }
        $value = MunicipalityAgeSexValue::create($base)->fresh();
        $this->assertSame('0.000000', $value->percentage);
        $this->assertNotNull($value->last_synced_at);
        foreach ([['ibge_code' => -1], ['sort_order' => 22], ['min_age' => -1], ['min_age' => 10, 'max_age' => 5]] as $invalid) {
            try {
                DB::transaction(fn () => AgeGroup::firstOrFail()->update($invalid));
                $this->fail('Invalid age group accepted.');
            } catch (QueryException) {
                $this->assertDatabaseCount('age_groups', 21);
            }
        }
    }
}
