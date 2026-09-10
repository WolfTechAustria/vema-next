<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tb_dutyplans', function (Blueprint $table) {
            $table->increments('planID');

            $table->string('name', 150);

            $table->date('date_from');
            $table->date('date_to');

            $table->boolean('exclude_holidays')
                ->default(true);

            $table->timestamp('created_at')
                ->useCurrent();

            $table->timestamp('updated_at')
                ->nullable();
        });

        Schema::table('tb_dutyplan_events', function (Blueprint $table) {
            $table->unsignedInteger('planID')
                ->nullable()
                ->after('eventID');

            $table->index('planID');
        });

        /*
         * Bestehende Termine übernehmen.
         */
        if (DB::table('tb_dutyplan_events')->exists()) {

            $minDate = DB::table('tb_dutyplan_events')
                ->min('duty_date');

            $maxDate = DB::table('tb_dutyplan_events')
                ->max('duty_date');

            if ($minDate && $maxDate) {

                $planId = DB::table('tb_dutyplans')->insertGetId([
                    'name' => 'Bestehender Dienstplan',
                    'date_from' => $minDate,
                    'date_to' => $maxDate,
                    'exclude_holidays' => true,
                    'created_at' => now(),
                ]);

                DB::table('tb_dutyplan_events')
                    ->whereNull('planID')
                    ->update([
                        'planID' => $planId,
                    ]);
            }
        }

        Schema::table('tb_dutyplan_events', function (Blueprint $table) {
            $table->foreign('planID')
                ->references('planID')
                ->on('tb_dutyplans')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tb_dutyplan_events', function (Blueprint $table) {
            $table->dropForeign(['planID']);
            $table->dropIndex(['planID']);
            $table->dropColumn('planID');
        });

        Schema::dropIfExists('tb_dutyplans');
    }
};
