<?php

namespace App\Services;

use App\Models\Member;
use App\Models\MembershipFeeEntry;
use App\Models\MembershipFeeYear;

/**
 * Beitragsjahre werden einmalig erzeugt (siehe MembershipFees/Index::createYear())
 * und legen dabei für jedes zu diesem Zeitpunkt aktive Mitglied einen Eintrag an.
 * Mitglieder, die danach eintreten (Neuanlage oder Wiedereintritt), bekommen
 * dadurch sonst nie einen Eintrag für bereits bestehende, noch offene
 * Beitragsjahre und sind dort weder sichtbar noch bearbeitbar. Dieser Service
 * holt das für ein einzelnes Mitglied nach.
 */
class MembershipFeeEntryService
{
    public function ensureEntriesForMember(Member $member): void
    {
        $joinYear = $member->dateOfJoin?->year;

        MembershipFeeYear::query()
            ->where('active', true)
            ->when($joinYear, fn ($query) => $query->where('year', '>=', $joinYear))
            ->get()
            ->each(function (MembershipFeeYear $year) use ($member) {
                MembershipFeeEntry::firstOrCreate(
                    [
                        'yearID' => $year->yearID,
                        'memberID' => $member->memberID,
                    ],
                    [
                        'amount' => $year->default_amount,
                        'status' => 'open',
                        'paid_at' => null,
                        'note' => null,
                    ]
                );
            });
    }
}
