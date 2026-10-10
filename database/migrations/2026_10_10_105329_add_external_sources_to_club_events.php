<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tb_event_sources', function (Blueprint $table) {
            $table->bigIncrements('sourceID');

            // Beschriftung, unter der die Mitglieder den Kalender sehen.
            $table->string('name', 150);

            // Verschlüsselt (Model-Cast), private Kalender-Links enthalten ein Geheimnis.
            $table->text('url');

            $table->boolean('rsvp_enabled')->default(false);
            $table->boolean('active')->default(true);

            $table->timestamp('last_synced_at')->nullable();
            $table->text('last_sync_error')->nullable();

            $table->timestamps();
        });

        Schema::table('tb_events', function (Blueprint $table) {
            $table->unsignedBigInteger('sourceID')->nullable()->after('eventID');
            $table->string('external_uid', 255)->nullable()->after('sourceID');

            $table->unique([
                'sourceID',
                'external_uid',
            ]);

            $table->foreign('sourceID')
                ->references('sourceID')
                ->on('tb_event_sources')
                ->cascadeOnDelete();
        });

        /*
         * Opt-in der Mitglieder: Termine dieser Quelle in ihr persönliches
         * iCal-Abo übernehmen (Standard: nein).
         */
        Schema::create('tb_member_event_source_subscriptions', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->integer('memberID');
            $table->unsignedBigInteger('sourceID');

            $table->timestamps();

            $table->unique([
                'memberID',
                'sourceID',
            ]);

            $table->foreign('sourceID')
                ->references('sourceID')
                ->on('tb_event_sources')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tb_member_event_source_subscriptions');

        Schema::table('tb_events', function (Blueprint $table) {
            $table->dropForeign(['sourceID']);
            $table->dropUnique(['sourceID', 'external_uid']);
            $table->dropColumn(['sourceID', 'external_uid']);
        });

        Schema::dropIfExists('tb_event_sources');
    }
};
