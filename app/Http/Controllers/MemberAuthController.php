<?php

namespace App\Http\Controllers;

use App\Models\MemberAccount;
use App\Models\MemberLoginToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MemberAuthController extends Controller
{
    public function magicLogin(
        Request $request,
        string $token
    ) {
        $tokenHash = hash('sha256', $token);

        $loginToken = MemberLoginToken::query()
            ->where('token_hash', $tokenHash)
            ->whereNull('used_at')
            ->first();

        abort_unless(
            $loginToken,
            403,
            'Dieser Login-Link ist ungültig oder wurde bereits verwendet.'
        );

        abort_if(
            $loginToken->isExpired(),
            403,
            'Dieser Login-Link ist abgelaufen.'
        );

        $member = $loginToken->member;

        abort_unless(
            $member && $member->active,
            403,
            'Dieses Mitglied ist nicht aktiv.'
        );

        $account = MemberAccount::firstOrCreate(
            [
                'memberID' => $member->memberID,
            ],
            [
                'email' => $loginToken->email,
                'email_verified_at' => now(),
            ]
        );

        if (!$account->email_verified_at) {
            $account->update([
                'email_verified_at' => now(),
            ]);
        }

        $loginToken->update([
            'used_at' => now(),
        ]);

        Auth::guard('member')->login(
            $account,
            remember: true
        );

        $request->session()->regenerate();

        $account->update([
            'last_login_at' => now(),
        ]);

        return redirect()->route(
            'member.profile'
        );
    }
    public function logout(Request $request)
    {
        Auth::guard('member')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('member.login');
    }

}
