<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tb_settings', function (Blueprint $table) {
            $table->unsignedInteger('invoice_reminder_first_after_days')->default(14);
            $table->unsignedInteger('invoice_reminder_interval_days')->default(14);
        });
    }

    public function down(): void
    {
        Schema::table('tb_settings', function (Blueprint $table) {
            $table->dropColumn([
                'invoice_reminder_first_after_days',
                'invoice_reminder_interval_days',
            ]);
        });
    }
};
