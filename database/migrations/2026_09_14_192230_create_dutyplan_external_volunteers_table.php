<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tb_dutyplan_external_volunteers', function (Blueprint $table) {
            $table->unsignedBigInteger('externalContactID')->primary();

            $table->boolean('active')->default(true);

            /*
             * Gleiches Bitmasken-System wie bei
             * tb_dutyplan_volunteers.
             *
             * 127 = alle 7 Wochentage verfügbar.
             */
            $table->unsignedInteger('weekday_mask')->default(127);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tb_dutyplan_external_volunteers');
    }
};
