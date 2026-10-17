<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Zweite Vereinsfarbe für Hervorhebungen (z. B. Datum, aktiver Menüpunkt).
     */
    public function up(): void
    {
        Schema::table('tb_homepage_settings', function (Blueprint $table) {
            $table->string('accent_color', 7)->default('#d7261e')->after('primary_color');
        });
    }

    public function down(): void
    {
        Schema::table('tb_homepage_settings', function (Blueprint $table) {
            $table->dropColumn('accent_color');
        });
    }
};
