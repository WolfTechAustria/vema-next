<?php

namespace App\Livewire\MemberPortal;

use Livewire\Component;
use Illuminate\Support\Facades\Auth;

class Profile extends Component
{
    public function render()
    {
        $account = Auth::guard('member')->user();

        abort_unless($account, 403);

        $member = $account->member()
            ->with([
                'city',
                'emails',
                'phones',
            ])
            ->firstOrFail();

        return view(
            'livewire.member-portal.profile',
            [
                'account' => $account,
                'member' => $member,
            ])->layout('layouts.app', [
            'title' => 'Mein Profil | VEMA',
            'heading' => 'Mein Profil',
        ]);
    }
}
