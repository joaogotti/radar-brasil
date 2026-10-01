<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('indicators', function (Blueprint $table): void {
            $table->jsonb('source_filters')->nullable();
            $table->text('source_universe')->nullable();
            $table->text('source_definition')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('indicators', fn (Blueprint $table) => $table->dropColumn(['source_filters', 'source_universe', 'source_definition']));
    }
};
