<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Einladungslink zur WhatsApp-Community des Vereins — erscheint als
     * Button in der Willkommens-E-Mail für neue Mitglieder.
     */
    public function up(): void
    {
        Schema::table('tb_settings', function (Blueprint $table) {
            $table->string('whatsapp_community_url', 255)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('tb_settings', function (Blueprint $table) {
            $table->dropColumn('whatsapp_community_url');
        });
    }
};
