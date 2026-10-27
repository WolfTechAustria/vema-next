<?php

namespace App\Services;

use App\Models\Member;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Geburtstagsliste eines Monats als PDF — für den Download in der
 * Mitgliederverwaltung und den monatlichen Versand an den Vorstand.
 */
class BirthdayListPdf
{
    public function __construct(
        public PdfLetterheadService $letterheadService,
    ) {}

    /**
     * Aktive Mitglieder mit Geburtstag im Monat, nach Tag sortiert.
     *
     * @return Collection<int, array{member: Member, age: int, isRound: bool, isHalfRound: bool}>
     */
    public function members(int $month): Collection
    {
        return Member::query()
            ->active()
            ->whereNotNull('dateOfBirth')
            ->whereMonth('dateOfBirth', $month)
            ->get()
            ->sortBy([
                fn (Member $a, Member $b) => Carbon::parse($a->dateOfBirth)->day <=> Carbon::parse($b->dateOfBirth)->day,
                ['surname', 'asc'],
                ['name', 'asc'],
            ])
            ->values()
            ->map(function (Member $member) {
                $age = $member->ageOn(now());

                return [
                    'member' => $member,
                    'age' => $age,
                    'isRound' => $member->isRoundBirthday($age),
                    'isHalfRound' => $member->isHalfRoundBirthday($age),
                ];
            });
    }

    /**
     * @param  Collection<int, array{member: Member, age: int, isRound: bool, isHalfRound: bool}>|null  $members
     */
    public function render(int $month, ?Collection $members = null): string
    {
        $pdf = Pdf::loadView('pdf.members-birthdays', [
            'members' => $members ?? $this->members($month),
            'monthName' => now()->locale('de')->month($month)->translatedFormat('F Y'),
            'year' => now()->year,
        ])->setPaper('a4', 'portrait');

        return $this->letterheadService->applyListBackground($pdf->output());
    }
}
