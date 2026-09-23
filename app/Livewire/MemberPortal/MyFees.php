<?php

namespace App\Livewire\MemberPortal;

use App\Models\MembershipFeeEntry;
use App\Services\ActiveMemberResolver;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class MyFees extends Component
{
    public function render(): View
    {
        $member = app(ActiveMemberResolver::class)->resolve();

        $entries = MembershipFeeEntry::query()
            ->where('memberID', $member->memberID)
            ->with([
                'year',
                'prescriptions' => fn ($query) => $query
                    ->whereNotNull('sent_at')
                    ->orderBy('sent_at'),
            ])
            ->get()
            ->sortByDesc(fn (MembershipFeeEntry $entry) => $entry->year?->year)
            ->values();

        return view('livewire.member-portal.my-fees', [
            'entries' => $entries,
            'openAmount' => $entries->where('status', 'open')->sum('amount'),
        ])->layout('layouts.app', [
            'title' => 'Meine Beiträge | VEMA',
            'heading' => 'Meine Beiträge',
        ]);
    }
}
