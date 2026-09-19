<?php
// database/migrations/2026_09_19_000002_create_duty_reminders_sent_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tb_duty_reminders_sent', function (Blueprint $table) {
            $table->id('reminderID');

            /*
             * Kein FK: tb_dutyplan_assignments ist eine Legacy-Tabelle
             * (siehe bestehende Migrationen im Projekt).
             */
            $table->unsignedBigInteger('assignmentID')->unique();

            $table->timestamp('sent_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tb_duty_reminders_sent');
    }
};
