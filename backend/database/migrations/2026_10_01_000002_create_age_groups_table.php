<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('age_groups', function (Blueprint $table): void {
            $table->id();
            $table->integer('ibge_code')->unique();
            $table->string('label', 50);
            $table->smallInteger('sort_order')->unique();
            $table->smallInteger('min_age');
            $table->smallInteger('max_age')->nullable();
            $table->timestamps();
        });
        $checks = [
            'age_group_code_check' => 'ibge_code > 0',
            'age_group_order_check' => 'sort_order BETWEEN 1 AND 21',
            'age_group_bounds_check' => 'min_age >= 0 AND (max_age IS NULL OR max_age >= min_age)',
        ];
        foreach ($checks as $name => $expression) {
            if (DB::getDriverName() === 'pgsql') {
                DB::statement("ALTER TABLE age_groups ADD CONSTRAINT {$name} CHECK ({$expression})");
            } else {
                $condition = preg_replace('/\b(ibge_code|sort_order|min_age|max_age)\b/', 'NEW.$1', $expression);
                foreach (['INSERT', 'UPDATE'] as $event) {
                    DB::unprepared("CREATE TRIGGER {$name}_{$event} BEFORE {$event} ON age_groups WHEN NOT ({$condition}) BEGIN SELECT RAISE(ABORT, 'Constraint {$name}'); END");
                }
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('age_groups');
    }
};
