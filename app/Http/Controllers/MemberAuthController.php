<?php

namespace App\Http\Controllers;

use App\Models\MemberAccount;
use App\Models\MemberLoginToken;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MemberAuthController extends Controller
{
    public function magicLogin(Request $request, string $token)
    {
        $tokenHash = hash('sha256', $token);

        $loginToken = MemberLoginToken::query()
            ->where('token_hash', $tokenHash)
            ->whereNull('used_at')
            ->first();

        if (!$loginToken) {
            ActivityLogger::log(
                'member.login_failed',
                'Anmeldeversuch mit ungültigem oder bereits verwendetem Login-Link.'
            );
        }

        abort_unless($loginToken, 403, 'Ungültiger oder bereits verwendeter Login-Link.');

        if ($loginToken->isExpired()) {
            ActivityLogger::log(
                'member.login_failed',
                'Anmeldeversuch mit abgelaufenem Login-Link für "' . $loginToken->email . '".'
            );
        }

        abort_if(
            $loginToken->isExpired(),
            403,
            'Dieser Login-Link ist abgelaufen.'
        );

        $members = \App\Models\Member::query()
            ->where('active', true)
            ->whereHas('emails', function ($query) use ($loginToken) {
                $query->where('email', $loginToken->email);
            })
            ->orderBy('surname')
            ->orderBy('name')
            ->get();

        if ($members->isEmpty()) {
            ActivityLogger::log(
                'member.login_failed',
                'Login-Link für "' . $loginToken->email . '" verwendet, aber kein aktives Mitglied gefunden.'
            );
        }

        abort_if(
            $members->isEmpty(),
            403,
            'Für diese E-Mail-Adresse wurde kein aktives Mitglied gefunden.'
        );

        $account = MemberAccount::firstOrCreate(
            [
                'email' => $loginToken->email,
            ],
            [
                'memberID' => $members->first()->memberID,
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

        ActivityLogger::log(
            'member.login',
            'Mitglied "' . $account->email . '" hat sich im Mitgliederbereich angemeldet.',
            'MemberAccount',
            $account->accountID
        );

        /*
         * Genau ein Mitglied:
         * direkt dieses Profil aktivieren.
         */
        if ($members->count() === 1) {

            $request->session()->put(
                'active_member_id',
                $members->first()->memberID
            );

            return redirect()->route('member.profile');
        }

        /*
         * Mehrere Mitglieder mit derselben E-Mail:
         * noch kein Profil auswählen.
         */
        $request->session()->forget('active_member_id');

        return redirect()->route('member.select-profile');
    }
    public function logout(Request $request)
    {
        $account = Auth::guard('member')->user();

        if ($account) {
            ActivityLogger::log(
                'member.logout',
                'Mitglied "' . $account->email . '" hat sich im Mitgliederbereich abgemeldet.',
                'MemberAccount',
                $account->accountID
            );
        }

        Auth::guard('member')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('member.login');
    }

}
