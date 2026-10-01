<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('municipality_indicator_values', function (Blueprint $table): void {
            $table->decimal('value', 24, 6)->nullable()->change();
            $table->string('status', 24)->default('available');
            $table->string('source_value', 64)->nullable();
            $table->timestampTz('last_synced_at')->nullable();
        });
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE municipality_indicator_values ADD CONSTRAINT municipal_value_status_check CHECK (status IN ('available','unavailable','not_applicable','suppressed','omitted')), ADD CONSTRAINT municipal_value_presence_check CHECK ((status = 'available') = (value IS NOT NULL))");
        } else {
            foreach (['INSERT', 'UPDATE'] as $event) {
                DB::unprepared("CREATE TRIGGER municipal_value_check_{$event} BEFORE {$event} ON municipality_indicator_values WHEN NEW.status NOT IN ('available','unavailable','not_applicable','suppressed','omitted') OR ((NEW.status = 'available') != (NEW.value IS NOT NULL)) BEGIN SELECT RAISE(ABORT, 'Invalid observation status/value'); END");
            }
        }
    }

    public function down(): void
    {
        if (DB::table('municipality_indicator_values')->whereNull('value')->orWhere('status', '!=', 'available')->exists()) {
            throw new RuntimeException('Cannot restore non-null values while unavailable observations exist.');
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE municipality_indicator_values DROP CONSTRAINT municipal_value_status_check, DROP CONSTRAINT municipal_value_presence_check');
        } else {
            foreach (['INSERT', 'UPDATE'] as $event) {
                DB::unprepared("DROP TRIGGER municipal_value_check_{$event}");
            }
        }
        Schema::table('municipality_indicator_values', function (Blueprint $table): void {
            $table->dropColumn(['status', 'source_value', 'last_synced_at']);
            $table->decimal('value', 24, 6)->nullable(false)->change();
        });
    }
};
