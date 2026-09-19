<?php
// database/migrations/2026_09_19_000001_create_member_duty_settings_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tb_member_duty_settings', function (Blueprint $table) {
            /*
             * 1:1 zu tb_members, analog zum bestehenden Muster von
             * tb_dutyplan_volunteers (memberID als Primary Key, keine
             * eigene ID, kein Autoincrement).
             */
            $table->integer('memberID')->primary();

            $table->boolean('duty_reminder_enabled')->default(true);

            $table->string('ical_token', 64)->nullable()->unique();
            $table->timestamp('ical_token_created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tb_member_duty_settings');
    }
};
