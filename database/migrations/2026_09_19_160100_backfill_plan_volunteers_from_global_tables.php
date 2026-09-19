<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $planIDs = DB::table('tb_dutyplans')->pluck('planID');

        if ($planIDs->isEmpty()) {
            return;
        }

        $now = now();

        $memberRows = [];

        foreach (DB::table('tb_dutyplan_volunteers')->get() as $volunteer) {
            foreach ($planIDs as $planID) {
                $memberRows[] = [
                    'planID' => $planID,
                    'memberID' => (int) $volunteer->memberID,
                    'externalContactID' => null,
                    'active' => (bool) $volunteer->active,
                    'weekday_mask' => (int) ($volunteer->weekday_mask ?? 127),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        foreach (array_chunk($memberRows, 500) as $chunk) {
            DB::table('tb_dutyplan_plan_volunteers')->insertOrIgnore($chunk);
        }

        if (Schema::hasTable('tb_dutyplan_external_volunteers')) {
            $externalRows = [];

            foreach (DB::table('tb_dutyplan_external_volunteers')->get() as $volunteer) {
                foreach ($planIDs as $planID) {
                    $externalRows[] = [
                        'planID' => $planID,
                        'memberID' => null,
                        'externalContactID' => (int) $volunteer->externalContactID,
                        'active' => (bool) $volunteer->active,
                        'weekday_mask' => (int) ($volunteer->weekday_mask ?? 127),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }

            foreach (array_chunk($externalRows, 500) as $chunk) {
                DB::table('tb_dutyplan_plan_volunteers')->insertOrIgnore($chunk);
            }
        }
    }

    public function down(): void
    {
        DB::table('tb_dutyplan_plan_volunteers')->delete();
    }
};
