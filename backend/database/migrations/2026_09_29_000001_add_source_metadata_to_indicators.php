<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('indicators', function (Blueprint $table): void {
            $table->string('source_type', 50)->nullable();
            $table->unsignedInteger('source_aggregate_id')->nullable()->change();
            $table->unsignedInteger('source_variable_id')->nullable()->change();
            $table->unsignedInteger('source_research_id')->nullable();
            $table->unsignedInteger('source_indicator_id')->nullable();
            $table->string('source_methodological_reference')->nullable();
            $table->string('source_series')->nullable();
        });

        DB::table('indicators')->update(['source_type' => 'ibge_aggregates']);

        Schema::table('indicators', function (Blueprint $table): void {
            $table->string('source_type', 50)->nullable(false)->change();
        });
    }

    public function down(): void
    {
        if (DB::table('indicators')->whereNull('source_aggregate_id')->orWhereNull('source_variable_id')
            ->orWhere('source_type', '!=', 'ibge_aggregates')->exists()) {
            throw new RuntimeException('Cannot restore the aggregate-only schema while indicators from other sources exist.');
        }

        Schema::table('indicators', function (Blueprint $table): void {
            $table->unsignedInteger('source_aggregate_id')->nullable(false)->change();
            $table->unsignedInteger('source_variable_id')->nullable(false)->change();
            $table->dropColumn(['source_type', 'source_research_id', 'source_indicator_id', 'source_methodological_reference', 'source_series']);
        });
    }
};
