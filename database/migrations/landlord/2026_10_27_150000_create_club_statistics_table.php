<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Kennzahlen je Verein (ohne Personendaten) für die Verbandszentrale.
        Schema::create('club_statistics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->unique()->constrained('tenants')->cascadeOnDelete();
            $table->json('data');
            $table->timestamp('collected_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('club_statistics');
    }
};
