<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('indicators', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 100)->unique();
            $table->string('name');
            $table->string('unit', 100);
            $table->string('source');
            $table->unsignedInteger('source_aggregate_id');
            $table->unsignedInteger('source_variable_id');
            $table->timestamps();
        });

        Schema::create('municipality_indicator_values', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('indicator_id')->constrained();
            $table->foreignId('municipality_id')->constrained();
            $table->decimal('value', 24, 6);
            $table->unsignedSmallInteger('reference_year');
            $table->timestamps();
            $table->unique(['municipality_id', 'indicator_id', 'reference_year'], 'municipality_indicator_year_unique');
            $table->index('indicator_id');
        });

        Schema::create('state_indicator_values', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('indicator_id')->constrained();
            $table->foreignId('state_id')->constrained();
            $table->decimal('value', 24, 6);
            $table->unsignedSmallInteger('reference_year');
            $table->timestamps();
            $table->unique(['state_id', 'indicator_id', 'reference_year'], 'state_indicator_year_unique');
            $table->index('indicator_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('state_indicator_values');
        Schema::dropIfExists('municipality_indicator_values');
        Schema::dropIfExists('indicators');
    }
};
