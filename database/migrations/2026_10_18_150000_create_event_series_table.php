<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Serientermine: Die Regel steht in tb_event_series, jeder einzelne
     * Termin der Serie ist ein eigener Eintrag in tb_events (eigene
     * Zu-/Absagen, iCal, Homepage).
     */
    public function up(): void
    {
        Schema::create('tb_event_series', function (Blueprint $table) {
            $table->bigIncrements('seriesID');

            // weekly, monthly_day oder monthly_weekday
            $table->string('frequency', 20);
            $table->unsignedTinyInteger('interval')->default(1);

            // ISO-Wochentage (1 = Montag) bei wöchentlichen Serien
            $table->json('weekdays')->nullable();

            // 1–4 bzw. -1 (letzter) bei „monatlich am n-ten Wochentag“
            $table->tinyInteger('month_week')->nullable();

            $table->date('until')->nullable();
            $table->unsignedSmallInteger('count')->nullable();

            // Einzeln gelöschte Termine, die nicht neu erzeugt werden sollen
            $table->json('excluded_dates')->nullable();

            $table->timestamps();
        });

        Schema::table('tb_events', function (Blueprint $table) {
            $table->unsignedBigInteger('seriesID')->nullable()->after('sourceID');

            $table->foreign('seriesID', 'tb_events_series_fk')
                ->references('seriesID')
                ->on('tb_event_series')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tb_events', function (Blueprint $table) {
            $table->dropForeign('tb_events_series_fk');
            $table->dropColumn('seriesID');
        });

        Schema::dropIfExists('tb_event_series');
    }
};
