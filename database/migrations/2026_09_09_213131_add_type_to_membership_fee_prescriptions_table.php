<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tb_membership_fee_prescriptions', function (Blueprint $table) {
            $table->string('type', 30)
                ->default('prescription')
                ->after('yearID');

            $table->unsignedInteger('reminder_level')
                ->nullable()
                ->after('type');
        });
    }

    public function down(): void
    {
        Schema::table('tb_membership_fee_prescriptions', function (Blueprint $table) {
            $table->dropColumn([
                'type',
                'reminder_level',
            ]);
        });
    }
};
