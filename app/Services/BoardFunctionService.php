<?php

namespace App\Services;

use App\Models\BoardFunction;
use App\Models\Member;
use App\Models\MemberBoardFunctionAssignment;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Verwaltet zeitraumbasierte Vorstandsfunktions-Zuweisungen. Beendete
 * Funktionen bleiben mit Von/Bis erhalten (Funktionsverlauf).
 *
 * Wer eine Funktion mit implies_schuetzenrat übernimmt (z. B. Schriftführer,
 * Kassier), erhält zusätzlich eine eigene Zuweisung der Rats-Funktion
 * (is_council, z. B. "Schützenrat"), außer bei gleichzeitiger Spitzenfunktion.
 * Sie ist unabhängig und endet nicht automatisch mit der auslösenden Funktion.
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

        return DB::transaction(function () use ($member, $boardFunctionID, $dateFrom): MemberBoardFunctionAssignment {
            $assignment = MemberBoardFunctionAssignment::create([
                'memberID' => $member->memberID,
                'boardFunctionID' => $boardFunctionID,
                'date_from' => $dateFrom,
                'date_to' => null,
            ]);

            if ($assignment->boardFunction->implies_schuetzenrat) {
                $this->assignSchuetzenratIfMissing($member, $dateFrom);
            }

            return $assignment;
        });
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
     * Aktuell laufende Funktionen eines Mitglieds.
     *
     * @return Collection<int, MemberBoardFunctionAssignment>
     */
    public function currentFunctionsFor(Member $member): Collection
    {
        return $member->boardFunctionAssignments()
            ->open()
            ->with('boardFunction')
            ->get()
            ->sortBy(fn (MemberBoardFunctionAssignment $assignment) => $assignment->boardFunction->sort_order)
            ->values();
    }

    /**
     * Beendete Funktionen eines Mitglieds, zuletzt beendete zuerst.
     *
     * @return Collection<int, MemberBoardFunctionAssignment>
     */
    public function formerFunctionsFor(Member $member): Collection
    {
        return $member->boardFunctionAssignments()
            ->whereNotNull('date_to')
            ->with('boardFunction')
            ->orderByDesc('date_to')
            ->orderByDesc('date_from')
            ->get();
    }

    private function assignSchuetzenratIfMissing(Member $member, ?string $dateFrom): void
    {
        $schuetzenrat = BoardFunction::active()->where('is_council', true)->first();

        if (! $schuetzenrat) {
            return;
        }

        $openFunctions = $member->boardFunctionAssignments()
            ->open()
            ->with('boardFunction')
            ->get();

        $hasTopVorstand = $openFunctions->contains(fn ($assignment) => $assignment->boardFunction->is_top_vorstand);
        $hasSchuetzenrat = $openFunctions->contains('boardFunctionID', $schuetzenrat->boardFunctionID);

        if ($hasTopVorstand || $hasSchuetzenrat) {
            return;
        }

        MemberBoardFunctionAssignment::create([
            'memberID' => $member->memberID,
            'boardFunctionID' => $schuetzenrat->boardFunctionID,
            'date_from' => $dateFrom,
            'date_to' => null,
        ]);
    }
}
