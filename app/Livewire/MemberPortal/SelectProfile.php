<?php

namespace App\Livewire\MemberPortal;

use App\Models\Member;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class SelectProfile extends Component
{
    public function selectProfile(int $memberID)
    {
        $account = Auth::guard('member')->user();

        abort_unless($account, 403);

        $member = Member::query()
            ->where('memberID', $memberID)
            ->where('active', true)
            ->whereHas('emails', function ($query) use ($account) {
                $query->where('email', $account->email);
            })
            ->firstOrFail();

        session([
            'active_member_id' => $member->memberID,
        ]);

        return redirect()->route('member.profile');
    }

    public function render()
    {
        $account = Auth::guard('member')->user();

        abort_unless($account, 403);

        $members = Member::query()
            ->where('active', true)
            ->whereHas('emails', function ($query) use ($account) {
                $query->where('email', $account->email);
            })
            ->orderBy('surname')
            ->orderBy('name')
            ->get();

        return view(
            'livewire.member-portal.select-profile',
            compact('account', 'members')
        )->layout('layouts.member-auth', [
            'title' => 'Profil auswählen | VEMA',
        ]);
    }
}
