<?php

namespace App\Http\Controllers;

use App\Models\Member;
use App\Models\MemberAccount;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * „Als Mitglied anmelden“: Ein Admin öffnet den Mitgliederbereich aus Sicht
 * eines Mitglieds. Die Verwaltungs-Anmeldung bleibt dabei bestehen, über
 * „Zurück zur Verwaltung“ wird nur die Mitglieder-Anmeldung beendet.
 */
class MemberImpersonationController extends Controller
{
    public const SESSION_KEY = 'member_impersonation_by';

    public function start(Request $request, Member $member): RedirectResponse
    {
        $admin = Auth::guard('web')->user();

        if (! $member->active) {
            return back()->with('error', 'Nur aktive Mitglieder haben Zugang zum Mitgliederbereich.');
        }

        $email = $this->loginEmail($member);

        if ($email === null) {
            return back()->with('error', 'Für den Mitgliederbereich braucht das Mitglied eine E-Mail-Adresse.');
        }

        $account = MemberAccount::firstOrCreate(
            ['email' => $email],
            ['memberID' => $member->memberID],
        );

        Auth::guard('member')->login($account);

        $request->session()->regenerate();
        $request->session()->put('active_member_id', $member->memberID);
        $request->session()->put(self::SESSION_KEY, $admin->getKey());

        ActivityLogger::log(
            'member.impersonation_started',
            '"'.$admin->username.'" hat den Mitgliederbereich als "'.$member->full_name.'" geöffnet.',
            'Member',
            $member->memberID
        );

        return redirect()->route('member.profile');
    }

    public function stop(Request $request): RedirectResponse
    {
        if (! $request->session()->has(self::SESSION_KEY)) {
            return redirect()->route('member.profile');
        }

        $memberID = $request->session()->get('active_member_id');

        Auth::guard('member')->logout();

        $request->session()->forget(['active_member_id', self::SESSION_KEY]);
        $request->session()->regenerate();

        return $memberID
            ? redirect()->route('members.show', $memberID)
            : redirect()->route('dashboard');
    }

    /**
     * Bevorzugt eine Adresse, für die schon ein Mitgliederkonto besteht.
     */
    private function loginEmail(Member $member): ?string
    {
        $emails = $member->emails()->pluck('email')->filter()->values();

        if ($emails->isEmpty()) {
            return null;
        }

        return MemberAccount::query()->whereIn('email', $emails)->value('email') ?? $emails->first();
    }
}
