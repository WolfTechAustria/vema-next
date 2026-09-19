<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tb_dutyplan_events', function (Blueprint $table) {
            $table->unsignedBigInteger('roleID')->nullable()->after('planID');
            $table->index('roleID');

            $table->time('start_time')->nullable()->after('required_helpers');
            $table->time('end_time')->nullable()->after('start_time');
        });
    }

    public function down(): void
    {
        Schema::table('tb_dutyplan_events', function (Blueprint $table) {
            $table->dropIndex(['roleID']);
            $table->dropColumn(['roleID', 'start_time', 'end_time']);
        });
    }
};
