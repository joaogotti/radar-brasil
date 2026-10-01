<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('municipality_age_sex_values', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('municipality_id')->constrained();
            $table->smallInteger('reference_year');
            $table->smallInteger('sex_code');
            $table->foreignId('age_group_id')->constrained();
            $table->integer('population')->nullable();
            $table->decimal('percentage', 9, 6)->nullable();
            foreach (['population', 'percentage'] as $measure) {
                $table->string($measure.'_status', 24);
                $table->string($measure.'_source_value', 64)->nullable();
            }
            $table->timestampTz('last_synced_at');
            $table->timestamps();
            $table->unique(['municipality_id', 'reference_year', 'sex_code', 'age_group_id'], 'municipality_age_sex_year_unique');
            $table->index('age_group_id');
        });
        $checks = [
            'age_sex_codes_check' => 'sex_code IN (4,5) AND reference_year > 0',
            'age_sex_population_check' => 'population IS NULL OR population >= 0',
            'age_sex_percentage_check' => 'percentage IS NULL OR percentage BETWEEN 0 AND 100',
            'age_sex_population_status_check' => "population_status IN ('available','unavailable','not_applicable','suppressed','omitted') AND ((population_status = 'available') = (population IS NOT NULL))",
            'age_sex_percentage_status_check' => "percentage_status IN ('available','unavailable','not_applicable','suppressed','omitted') AND ((percentage_status = 'available') = (percentage IS NOT NULL))",
        ];
        foreach ($checks as $name => $expression) {
            if (DB::getDriverName() === 'pgsql') {
                DB::statement("ALTER TABLE municipality_age_sex_values ADD CONSTRAINT {$name} CHECK ({$expression})");
            } else {
                $condition = preg_replace('/\b(sex_code|reference_year|population|percentage|population_status|percentage_status)\b/', 'NEW.$1', $expression);
                foreach (['INSERT', 'UPDATE'] as $event) {
                    DB::unprepared("CREATE TRIGGER {$name}_{$event} BEFORE {$event} ON municipality_age_sex_values WHEN NOT ({$condition}) BEGIN SELECT RAISE(ABORT, 'Constraint {$name}'); END");
                }
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('municipality_age_sex_values');
    }
};
