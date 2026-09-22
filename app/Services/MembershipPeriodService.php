<?php

namespace App\Services;

use App\Models\Member;
use App\Models\MemberMembershipPeriod;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Verwaltet die Vereinszugehörigkeit eines Mitglieds als Folge von
 * Zeiträumen (Ein-/Austritt/Wiedereintritt), statt eines reinen
 * active-Flags. Hält tb_members.active synchron, damit die zahlreichen
 * bestehenden Konsumenten dieses Feldes unverändert weiterfunktionieren.
 */
class MembershipPeriodService
{
    public function openInitialPeriod(Member $member, ?string $dateFrom): MemberMembershipPeriod
    {
        return MemberMembershipPeriod::create([
            'memberID' => $member->memberID,
            'date_from' => $dateFrom,
            'date_to' => null,
        ]);
    }

    public function resign(Member $member, string $dateTo, ?string $note = null): MemberMembershipPeriod
    {
        $openPeriod = $member->currentMembershipPeriod;

        abort_if(!$openPeriod, 422, 'Dieses Mitglied ist bereits ausgetreten.');

        abort_if(
            $openPeriod->date_from && $dateTo < $openPeriod->date_from->format('Y-m-d'),
            422,
            'Das Austrittsdatum darf nicht vor dem Eintrittsdatum liegen.'
        );

        return DB::transaction(function () use ($member, $openPeriod, $dateTo, $note) {
            $openPeriod->update([
                'date_to' => $dateTo,
                'note' => $note,
            ]);

            $member->update(['active' => false]);

            return $openPeriod->fresh();
        });
    }

    public function reenter(Member $member, string $dateFrom, ?string $note = null): MemberMembershipPeriod
    {
        abort_if($member->currentMembershipPeriod, 422, 'Dieses Mitglied ist bereits aktiv.');

        $lastClosedPeriod = $member->membershipPeriods()
            ->closed()
            ->orderByDesc('date_to')
            ->first();

        abort_if(
            $lastClosedPeriod && $lastClosedPeriod->date_to && $dateFrom < $lastClosedPeriod->date_to->format('Y-m-d'),
            422,
            'Das Wiedereintrittsdatum darf nicht vor dem letzten Austrittsdatum liegen.'
        );

        return DB::transaction(function () use ($member, $dateFrom, $note) {
            $period = MemberMembershipPeriod::create([
                'memberID' => $member->memberID,
                'date_from' => $dateFrom,
                'date_to' => null,
                'note' => $note,
            ]);

            $member->update(['active' => true]);

            return $period;
        });
    }

    public function searchInactiveByName(string $name, string $surname): Collection
    {
        return Member::query()
            ->where('active', 0)
            ->where(function ($query) use ($name, $surname) {
                $query->where('name', 'like', "%{$name}%")
                    ->orWhere('surname', 'like', "%{$surname}%");
            })
            ->with(['membershipPeriods' => function ($query) {
                $query->closed()->orderByDesc('date_to')->limit(1);
            }])
            ->orderBy('surname')
            ->orderBy('name')
            ->limit(10)
            ->get();
    }
}
