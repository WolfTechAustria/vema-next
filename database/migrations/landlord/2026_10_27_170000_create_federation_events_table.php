<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Für die Mitgliedsvereine freigegebene Termine eines Verbands (Kopie aus dessen tb_events).
        Schema::create('federation_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('federation_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('uid', 100);
            $table->string('title', 150);
            $table->text('description')->nullable();
            $table->string('location', 150)->nullable();
            // Wanduhrzeit wie in tb_events
            $table->dateTime('starts_at');
            $table->dateTime('ends_at')->nullable();
            $table->boolean('all_day')->default(false);
            $table->unique(['federation_id', 'uid']);
            $table->index(['federation_id', 'starts_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('federation_events');
    }
};
