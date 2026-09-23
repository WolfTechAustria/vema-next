<?php

namespace App\Services;

use App\Models\Member;
use Illuminate\Support\Facades\Auth;

class ActiveMemberResolver
{
    /**
     * Liefert das im Mitgliederbereich ausgewählte Mitglied.
     *
     * Das Mitglied muss aktiv sein UND die Login-E-Mail des Accounts
     * muss beim Mitglied hinterlegt sein. Dadurch kann nicht einfach
     * eine fremde memberID in die Session geschrieben werden.
     */
    public function resolve(): Member
    {
        $account = Auth::guard('member')->user();

        abort_unless($account, 403);

        $memberID = session('active_member_id');

        abort_unless($memberID, 403, 'Kein Mitgliederprofil ausgewählt.');

        return Member::query()
            ->where('memberID', $memberID)
            ->where('active', true)
            ->whereHas('emails', function ($query) use ($account) {
                $query->where('email', $account->email);
            })
            ->firstOrFail();
    }
}
