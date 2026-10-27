<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Mitgliedsvereine eines Verbands (Einladung + Zustimmung des Vereins).
        Schema::create('federation_memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('federation_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('club_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('status', 20)->index();
            $table->timestamp('invited_at')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();
            $table->unique(['federation_id', 'club_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('federation_memberships');
    }
};
