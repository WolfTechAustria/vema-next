<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tb_dutyplan_events', function (Blueprint $table) {
            $table->dropUnique('uq_dutyplan_date_type');
            $table->unique(['planID', 'duty_date', 'duty_type'], 'uq_dutyplan_plan_date_type');
        });
    }

    public function down(): void
    {
        Schema::table('tb_dutyplan_events', function (Blueprint $table) {
            $table->dropUnique('uq_dutyplan_plan_date_type');
            $table->unique(['duty_date', 'duty_type'], 'uq_dutyplan_date_type');
        });
    }
};
