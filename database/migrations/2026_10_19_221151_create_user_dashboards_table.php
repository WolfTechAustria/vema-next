<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Persönliche Dashboard-Anordnung je Staff-Benutzer: geordnete Liste der
     * sichtbaren Modul-Schlüssel (siehe App\Enums\DashboardModule).
     */
    public function up(): void
    {
        Schema::create('tb_user_dashboards', function (Blueprint $table) {
            $table->id('userDashboardID');

            // Kein FK: tb_user.id ist ein signed int (Legacy-Tabelle).
            $table->integer('userID')->unique();

            $table->json('modules');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tb_user_dashboards');
    }
};
