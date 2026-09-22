<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Mitglieder, die nach der Erzeugung eines Beitragsjahres (createYear())
     * angelegt wurden, hatten bislang keinen Eintrag in tb_membership_fee_entries
     * für bereits bestehende, noch offene Beitragsjahre und konnten dadurch
     * weder angezeigt noch als bezahlt markiert werden. Holt das für alle
     * aktuell aktiven Mitglieder und aktiven (nicht abgeschlossenen) Beitragsjahre nach.
     */
    public function up(): void
    {
        $activeYears = DB::table('tb_membership_fee_years')
            ->where('active', true)
            ->get(['yearID', 'year', 'default_amount']);

        $activeMembers = DB::table('tb_members')
            ->where('active', 1)
            ->get(['memberID', 'dateOfJoin']);

        $now = now();
        $rows = [];

        foreach ($activeMembers as $member) {
            $joinYear = $member->dateOfJoin ? (int) date('Y', strtotime($member->dateOfJoin)) : null;

            foreach ($activeYears as $year) {
                if ($joinYear !== null && $year->year < $joinYear) {
                    continue;
                }

                $rows[] = [
                    'yearID' => $year->yearID,
                    'memberID' => $member->memberID,
                    'amount' => $year->default_amount,
                    'status' => 'open',
                    'paid_at' => null,
                    'note' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('tb_membership_fee_entries')->insertOrIgnore($chunk);
        }
    }

    public function down(): void
    {
        /*
         * Nur die von dieser Migration erzeugten, seither unangetasteten
         * ("open", nie bezahlt) Einträge wieder entfernen.
         */
        $activeYears = DB::table('tb_membership_fee_years')
            ->where('active', true)
            ->pluck('yearID');

        $activeMemberIDs = DB::table('tb_members')
            ->where('active', 1)
            ->pluck('memberID');

        DB::table('tb_membership_fee_entries')
            ->whereIn('yearID', $activeYears)
            ->whereIn('memberID', $activeMemberIDs)
            ->where('status', 'open')
            ->whereNull('paid_at')
            ->whereNull('note')
            ->delete();
    }
};
