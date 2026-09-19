<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $unassigned = DB::table('tb_dutyplan_events')->whereNull('roleID')->count();

        if ($unassigned > 0) {
            throw new \RuntimeException(
                "Abbruch: {$unassigned} tb_dutyplan_events-Zeile(n) haben noch keine roleID. "
                . 'Die Backfill-Migration (2026_09_19_150200) muss zuerst erfolgreich durchlaufen sein.'
            );
        }

        $duplicates = DB::table('tb_dutyplan_events')
            ->select('planID', 'duty_date', 'roleID')
            ->groupBy('planID', 'duty_date', 'roleID')
            ->havingRaw('COUNT(*) > 1')
            ->count();

        if ($duplicates > 0) {
            throw new \RuntimeException(
                "Abbruch: {$duplicates} doppelte (planID, duty_date, roleID)-Kombination(en) in "
                . 'tb_dutyplan_events gefunden — der neue Unique-Index würde fehlschlagen.'
            );
        }

        DB::statement('ALTER TABLE tb_dutyplan_events DROP INDEX uq_dutyplan_plan_date_type');

        Schema::table('tb_dutyplan_events', function (Blueprint $table) {
            $table->unsignedBigInteger('roleID')->nullable(false)->change();

            $table->unique(['planID', 'duty_date', 'roleID'], 'uq_dutyplan_plan_date_role');

            $table->foreign('roleID')
                ->references('roleID')
                ->on('tb_dutyplan_roles')
                ->restrictOnDelete();

            $table->dropColumn('duty_type');
        });
    }

    public function down(): void
    {
        Schema::table('tb_dutyplan_events', function (Blueprint $table) {
            $table->string('duty_type')->nullable()->after('planID');
        });

        DB::table('tb_dutyplan_events')
            ->orderBy('eventID')
            ->chunkById(500, function ($rows) {
                foreach ($rows as $row) {
                    DB::table('tb_dutyplan_events')
                        ->where('eventID', $row->eventID)
                        ->update([
                            'duty_type' => 'role_' . $row->roleID,
                        ]);
                }
            }, 'eventID');

        Schema::table('tb_dutyplan_events', function (Blueprint $table) {
            $table->dropForeign(['roleID']);
            $table->dropUnique('uq_dutyplan_plan_date_role');
            $table->unsignedBigInteger('roleID')->nullable()->change();
        });

        DB::statement(
            'ALTER TABLE tb_dutyplan_events ADD UNIQUE KEY uq_dutyplan_plan_date_type (planID, duty_date, duty_type)'
        );
    }
};
