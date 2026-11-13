<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tb_settings', function (Blueprint $table) {
            $table->boolean('duty_swaps_enabled')->default(true);
            $table->boolean('duty_swap_requires_approval')->default(false);
            $table->unsignedSmallInteger('duty_swap_deadline_hours')->default(0);
            $table->text('duty_swap_notify_emails')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('tb_settings', function (Blueprint $table) {
            $table->dropColumn([
                'duty_swaps_enabled',
                'duty_swap_requires_approval',
                'duty_swap_deadline_hours',
                'duty_swap_notify_emails',
            ]);
        });
    }
};
