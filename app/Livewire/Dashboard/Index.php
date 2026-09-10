<?php

namespace App\Livewire\Dashboard;

use App\Models\Member;
use Livewire\Component;
use App\Models\MembershipFeeEntry;
use App\Models\MembershipFeeYear;

class Index extends Component
{
    public function render()
    {
        return view('livewire.dashboard.index', [
            'activeMembers' => Member::where('active', 1)->count(),
            'allMembers' => Member::count(),
        ])->layout('layouts.app', [
            'title' => 'Dashboard | VEMA',
            'heading' => 'Dashboard',
        ]);
    }


    public function membershipFeeActions(): array
    {
        $year = MembershipFeeYear::query()
            ->orderByDesc('year')
            ->first();

        if (!$year) {
            return [
                'reminder_due' => 0,
                'open_without_email' => 0,
            ];
        }

        $entries = MembershipFeeEntry::query()
            ->with([
                'member.emails',
                'year',
                'prescriptions',
            ])
            ->where('yearID', $year->yearID)
            ->where('status', 'open')
            ->whereHas('member', fn ($query) =>
            $query->where('active', 1)
            )
            ->get();

        return [
            'reminder_due' => $entries
                ->filter(fn ($entry) => $entry->isReminderDue())
                ->count(),

            'open_without_email' => $entries
                ->filter(fn ($entry) =>
                $entry->member?->emails?->isEmpty()
                )
                ->count(),
        ];
    }
    public function membershipFeeStats(): array
    {
        $year = MembershipFeeYear::query()
            ->orderByDesc('year')
            ->first();

        if (!$year) {
            return [
                'year' => null,
                'open' => 0,
                'paid' => 0,
                'exempt' => 0,
                'reminder_due' => 0,
                'reminded' => 0,

            ];
        }

        $entries = MembershipFeeEntry::query()
            ->with([
                'member',
                'year',
                'prescriptions',
            ])
            ->where('yearID', $year->yearID)
            ->whereHas('member', fn ($query) =>
            $query->where('active', 1)
            )
            ->get();

        return [
            'year' => $year->year,

            'open' => $entries
                ->where('status', 'open')
                ->count(),

            'paid' => $entries
                ->where('status', 'paid')
                ->count(),

            'exempt' => $entries
                ->where('status', 'exempt')
                ->count(),

            'reminder_due' => $entries
                ->filter(fn ($entry) =>
                $entry->isReminderDue()
                )
                ->count(),

            'reminded' => $entries
                ->filter(fn ($entry) =>
                $entry->prescriptions
                    ->where('type', 'reminder')
                    ->isNotEmpty()
                )
                ->count(),
            'open_amount' => $entries
                ->where('status', 'open')
                ->sum(fn ($entry) => (float) ($entry->amount ?? 0)),

            'paid_amount' => $entries
                ->where('status', 'paid')
                ->sum(fn ($entry) => (float) ($entry->amount ?? 0)),
        ];
    }
}
