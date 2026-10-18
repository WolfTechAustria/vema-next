<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Bisher wurde "Schützenrat" für Schriftführer/Kassier(-Stv.) nur zur
     * Laufzeit abgeleitet und verschwand mit dem Ende der Funktion. Ab jetzt
     * ist er eine eigene Zuweisung — für alle bisher abgeleiteten Fälle wird
     * sie hier angelegt (Beginn = früheste auslösende Funktion).
     */
    public function up(): void
    {
        $schuetzenratID = DB::table('tb_board_functions')
            ->where('name', 'Schützenrat')
            ->value('boardFunctionID');

        if (! $schuetzenratID) {
            return;
        }

        $openAssignments = DB::table('tb_member_board_function_assignments as assignment')
            ->join('tb_board_functions as function', 'function.boardFunctionID', '=', 'assignment.boardFunctionID')
            ->whereNull('assignment.date_to')
            ->select('assignment.memberID', 'assignment.boardFunctionID', 'assignment.date_from', 'function.is_top_vorstand', 'function.implies_schuetzenrat')
            ->get()
            ->groupBy('memberID');

        $now = now();

        foreach ($openAssignments as $memberID => $assignments) {
            $implying = $assignments->where('implies_schuetzenrat', true);

            if ($implying->isEmpty()
                || $assignments->contains('is_top_vorstand', true)
                || $assignments->contains('boardFunctionID', $schuetzenratID)) {
                continue;
            }

            DB::table('tb_member_board_function_assignments')->insert([
                'memberID' => $memberID,
                'boardFunctionID' => $schuetzenratID,
                'date_from' => $implying->pluck('date_from')->filter()->min(),
                'date_to' => null,
                'created_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        //
    }
};
