<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Legacy-Codes aus tb_members.board_function → Name in tb_board_functions.
     * Bestätigte Zuordnung, siehe Plan.
     */
    private const LEGACY_BOARD_FUNCTION_NAMES = [
        1 => 'Oberschützenmeister',
        2 => '1. Schützenmeister',
        3 => '2. Schützenmeister',
        4 => 'Schriftführer',
        5 => 'Schriftführer Stv.',
        6 => 'Kassier',
        7 => 'Kassier Stv.',
        8 => 'Schützenrat',
    ];

    public function up(): void
    {
        $now = now();

        $boardFunctionIDsByName = DB::table('tb_board_functions')
            ->pluck('boardFunctionID', 'name');

        $members = DB::table('tb_members')
            ->select('memberID', 'active', 'dateOfJoin', 'deactiveSince', 'board_function')
            ->orderBy('memberID')
            ->get();

        foreach ($members as $member) {
            DB::table('tb_member_membership_periods')->insert([
                'memberID' => $member->memberID,
                'date_from' => $member->dateOfJoin,
                'date_to' => $member->active ? null : $member->deactiveSince,
                'note' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            if ($member->board_function !== null
                && isset(self::LEGACY_BOARD_FUNCTION_NAMES[$member->board_function])
            ) {
                $name = self::LEGACY_BOARD_FUNCTION_NAMES[$member->board_function];
                $boardFunctionID = $boardFunctionIDsByName[$name] ?? null;

                if ($boardFunctionID) {
                    DB::table('tb_member_board_function_assignments')->insert([
                        'memberID' => $member->memberID,
                        'boardFunctionID' => $boardFunctionID,
                        'date_from' => null,
                        'date_to' => null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        DB::table('tb_member_board_function_assignments')->truncate();
        DB::table('tb_member_membership_periods')->truncate();
    }
};
