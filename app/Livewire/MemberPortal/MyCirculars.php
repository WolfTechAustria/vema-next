<?php

namespace App\Livewire\MemberPortal;

use App\Models\CircularRecipient;
use App\Services\ActiveMemberResolver;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class MyCirculars extends Component
{
    public function render(): View
    {
        $member = app(ActiveMemberResolver::class)->resolve();

        $receipts = CircularRecipient::query()
            ->where('memberID', $member->memberID)
            ->whereNotNull('sent_at')
            ->whereHas('circular')
            ->with('circular.attachments')
            ->orderByDesc('sent_at')
            ->get();

        return view('livewire.member-portal.my-circulars', [
            'receipts' => $receipts,
        ])->layout('layouts.app', [
            'title' => 'Meine Rundschreiben | VEMA',
            'heading' => 'Meine Rundschreiben',
        ]);
    }
}
