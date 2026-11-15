<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Anwesenheit je Termin: eine Zeile pro erfasstem Mitglied (anwesend ja/nein).
        Schema::create('tb_event_attendance', function (Blueprint $table) {
            $table->bigIncrements('attendanceID');
            $table->unsignedBigInteger('eventID');
            // memberID ist in tb_members ein signed int — daher ohne Fremdschlüssel.
            $table->integer('memberID')->index();
            $table->boolean('present')->default(false);
            $table->timestamps();

            $table->unique(['eventID', 'memberID']);
            $table->foreign('eventID')->references('eventID')->on('tb_events')->cascadeOnDelete();
        });

        // Nur Termine mit erfasster Anwesenheit zählen für die Quote.
        Schema::table('tb_events', function (Blueprint $table) {
            $table->timestamp('attendance_taken_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('tb_events', function (Blueprint $table) {
            $table->dropColumn('attendance_taken_at');
        });

        Schema::dropIfExists('tb_event_attendance');
    }
};
