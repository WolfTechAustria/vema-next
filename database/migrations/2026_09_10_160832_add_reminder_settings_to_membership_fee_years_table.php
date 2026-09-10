<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tb_membership_fee_years', function (Blueprint $table) {
            $table->unsignedInteger('first_reminder_after_days')
                ->default(14)
                ->after('due_date');

            $table->unsignedInteger('reminder_interval_days')
                ->default(14)
                ->after('first_reminder_after_days');

            $table->unsignedInteger('max_reminders')
                ->default(3)
                ->after('reminder_interval_days');
        });
    }

    public function down(): void
    {
        Schema::table('tb_membership_fee_years', function (Blueprint $table) {
            $table->dropColumn([
                'first_reminder_after_days',
                'reminder_interval_days',
                'max_reminders',
            ]);
        });
    }
};
