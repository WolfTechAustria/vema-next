<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Freigaben von Diensten zwischen Mitgliedern: "ownerID" gibt seine
     * Dienste frei, "viewerID" sieht sie im eigenen oder einem eigenen
     * Kalender-Abo.
     */
    public function up(): void
    {
        Schema::create('tb_duty_calendar_shares', function (Blueprint $table) {
            $table->id('shareID');
            $table->integer('ownerID')->index();
            $table->integer('viewerID')->index();
            $table->boolean('in_own_calendar')->default(false);
            $table->string('ical_token', 64)->nullable()->unique();
            $table->timestamp('ical_token_created_at')->nullable();
            $table->timestamps();

            $table->unique(['ownerID', 'viewerID']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tb_duty_calendar_shares');
    }
};
