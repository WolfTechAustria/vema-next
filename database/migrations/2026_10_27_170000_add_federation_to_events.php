<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Verband: Termin für die Mitgliedsvereine freigeben.
        Schema::table('tb_events', function (Blueprint $table) {
            $table->boolean('share_with_clubs')->default(false)->after('show_on_homepage');
        });

        // Verein: Kalender eines Verbands statt eines iCal-Links.
        Schema::table('tb_event_sources', function (Blueprint $table) {
            $table->unsignedBigInteger('federation_id')->nullable()->after('name');
            $table->text('url')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('tb_event_sources', function (Blueprint $table) {
            $table->dropColumn('federation_id');
        });

        Schema::table('tb_events', function (Blueprint $table) {
            $table->dropColumn('share_with_clubs');
        });
    }
};
