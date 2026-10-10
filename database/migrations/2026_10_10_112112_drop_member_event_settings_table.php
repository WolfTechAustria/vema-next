<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Vereinstermine laufen jetzt über denselben Kalender-Abo-Link wie der
 * Dienstplan (Token in tb_member_duty_settings) — der eigene Termin-Token
 * wird nicht mehr gebraucht.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('tb_member_event_settings');
    }

    public function down(): void
    {
        Schema::create('tb_member_event_settings', function (Blueprint $table) {
            $table->integer('memberID')->primary();

            $table->string('ical_token', 64)->nullable()->unique();
            $table->timestamp('ical_token_created_at')->nullable();
        });
    }
};
