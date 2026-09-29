<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('municipalities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('state_id')->constrained();
            $table->unsignedInteger('ibge_code')->unique();
            $table->string('name', 100);
            $table->timestamps();
            $table->index('state_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('municipalities');
    }
};
