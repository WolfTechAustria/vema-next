<?php

namespace App\Services;

use App\Models\BoardFunction;
use App\Models\Member;
use App\Models\MemberBoardFunctionAssignment;
use Illuminate\Support\Collection;

/**
 * Verwaltet zeitraumbasierte Vorstandsfunktions-Zuweisungen und leitet
 * die "Schützenrat"-Zugehörigkeit für Schriftführer/Kassier(-Stv.) automatisch
 * ab, solange diese nicht gleichzeitig eine Spitzenfunktion (Oberschützenmeister,
 * 1./2. Schützenmeister) innehaben.
 */
class BoardFunctionService
{
    public function assign(Member $member, int $boardFunctionID, ?string $dateFrom, ?string $note = null): MemberBoardFunctionAssignment
    {
        $alreadyOpen = $member->boardFunctionAssignments()
            ->open()
            ->where('boardFunctionID', $boardFunctionID)
            ->exists();

        abort_if($alreadyOpen, 422, 'Diese Funktion ist bereits zugewiesen.');

        return MemberBoardFunctionAssignment::create([
            'memberID' => $member->memberID,
            'boardFunctionID' => $boardFunctionID,
            'date_from' => $dateFrom,
            'date_to' => null,
        ]);
    }

    public function end(MemberBoardFunctionAssignment $assignment, string $dateTo): MemberBoardFunctionAssignment
    {
        abort_if($assignment->date_to !== null, 422, 'Diese Funktion wurde bereits beendet.');

        abort_if(
            $assignment->date_from && $dateTo < $assignment->date_from->format('Y-m-d'),
            422,
            'Das Enddatum darf nicht vor dem Beginn liegen.'
        );

        $assignment->update(['date_to' => $dateTo]);

        return $assignment->fresh();
    }

    /**
     * Aktuell effektive Funktionen eines Mitglieds, inkl. automatisch
     * abgeleitetem "Schützenrat" für Schriftführer/Kassier(-Stv.), sofern
     * keine Spitzenfunktion gleichzeitig vorliegt und "Schützenrat" nicht
     * bereits explizit zugewiesen ist.
     *
     * @return Collection<int, array{boardFunction: BoardFunction, assignment: ?MemberBoardFunctionAssignment, derived: bool}>
     */
    public function currentFunctionsFor(Member $member): Collection
    {
        $openAssignments = $member->boardFunctionAssignments()
            ->open()
            ->with('boardFunction')
            ->get();

        $entries = $openAssignments->map(fn (MemberBoardFunctionAssignment $assignment) => [
            'boardFunction' => $assignment->boardFunction,
            'assignment' => $assignment,
            'derived' => false,
        ]);

        $hasTopVorstand = $openAssignments->contains(fn ($a) => $a->boardFunction->is_top_vorstand);
        $hasSchuetzenratImplying = $openAssignments->contains(fn ($a) => $a->boardFunction->implies_schuetzenrat);
        $hasExplicitSchuetzenrat = $openAssignments->contains(fn ($a) => $a->boardFunction->name === 'Schützenrat');

        if ($hasSchuetzenratImplying && !$hasTopVorstand && !$hasExplicitSchuetzenrat) {
            $schuetzenrat = BoardFunction::where('name', 'Schützenrat')->first();

            if ($schuetzenrat) {
                $entries->push([
                    'boardFunction' => $schuetzenrat,
                    'assignment' => null,
                    'derived' => true,
                ]);
            }
        }

        return $entries->sortBy(fn ($entry) => $entry['boardFunction']->sort_order)->values();
    }
}
