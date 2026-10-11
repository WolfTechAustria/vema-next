<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Geänderte Felder je Protokolleintrag: Liste aus
     * {"field": "E-Mail", "old": "…", "new": "…", "masked": false}.
     */
    public function up(): void
    {
        Schema::table('tb_activity_log', function (Blueprint $table) {
            $table->json('changes')->nullable()->after('subject_id');
        });
    }

    public function down(): void
    {
        Schema::table('tb_activity_log', function (Blueprint $table) {
            $table->dropColumn('changes');
        });
    }
};
