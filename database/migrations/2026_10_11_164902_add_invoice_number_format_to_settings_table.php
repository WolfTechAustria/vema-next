<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Frei einstellbarer Nummernkreis für Rechnungen, z. B. "{JAHR}-{NR:4}"
     * (bisheriges festes Format) oder "{JAHR}{NR:5}".
     */
    public function up(): void
    {
        Schema::table('tb_settings', function (Blueprint $table) {
            $table->string('invoice_number_format', 50)->default('{JAHR}-{NR:4}');
        });
    }

    public function down(): void
    {
        Schema::table('tb_settings', function (Blueprint $table) {
            $table->dropColumn('invoice_number_format');
        });
    }
};
