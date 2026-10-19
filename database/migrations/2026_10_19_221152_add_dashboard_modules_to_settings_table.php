<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Vereinsstandard für das Dashboard: gilt für alle Benutzer ohne eigene
     * Anordnung. Leer = eingebaute Standard-Anordnung.
     */
    public function up(): void
    {
        Schema::table('tb_settings', function (Blueprint $table) {
            $table->json('dashboard_modules')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('tb_settings', function (Blueprint $table) {
            $table->dropColumn('dashboard_modules');
        });
    }
};
