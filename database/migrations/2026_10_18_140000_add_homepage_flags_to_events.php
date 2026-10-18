<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Vereinstermine auf der öffentlichen Homepage: nur ausdrücklich
     * freigegebene Termine bzw. Quellen erscheinen dort.
     */
    public function up(): void
    {
        Schema::table('tb_events', function (Blueprint $table) {
            $table->boolean('show_on_homepage')->default(false)->after('rsvp_enabled');
        });

        Schema::table('tb_event_sources', function (Blueprint $table) {
            $table->boolean('show_on_homepage')->default(false)->after('active');
        });

        Schema::table('tb_homepage_settings', function (Blueprint $table) {
            $table->boolean('show_events')->default(true)->after('show_board_photos');
        });
    }

    public function down(): void
    {
        Schema::table('tb_homepage_settings', function (Blueprint $table) {
            $table->dropColumn('show_events');
        });

        Schema::table('tb_event_sources', function (Blueprint $table) {
            $table->dropColumn('show_on_homepage');
        });

        Schema::table('tb_events', function (Blueprint $table) {
            $table->dropColumn('show_on_homepage');
        });
    }
};
