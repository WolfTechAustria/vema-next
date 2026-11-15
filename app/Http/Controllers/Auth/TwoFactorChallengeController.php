<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\TwoFactorAuthentication;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * Zweiter Schritt der Anmeldung: Code aus der App, per E-Mail oder ein
 * Wiederherstellungscode.
 */
class TwoFactorChallengeController extends Controller
{
    public const SESSION_KEY = 'two_factor_login';

    private const MAX_ATTEMPTS = 5;

    public function create(Request $request): View|RedirectResponse
    {
        $user = $this->pendingUser($request);

        if (! $user) {
            return redirect()->route('login');
        }

        return view('auth.two-factor-challenge', [
            'user' => $user,
            'usesEmail' => $user->two_factor_method === TwoFactorAuthentication::METHOD_EMAIL,
            'trustDays' => TwoFactorAuthentication::TRUST_DAYS,
        ]);
    }

    public function store(Request $request, TwoFactorAuthentication $twoFactor): RedirectResponse
    {
        $user = $this->pendingUser($request);

        if (! $user) {
            return redirect()->route('login')->withErrors(['username' => 'Die Anmeldung ist abgelaufen. Bitte melde dich erneut an.']);
        }

        $request->validate(['code' => ['required', 'string', 'max:20']], ['code.required' => 'Bitte gib den Code ein.']);

        $throttleKey = 'two-factor-login:'.$user->id.'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_ATTEMPTS)) {
            $request->session()->forget(self::SESSION_KEY);

            return redirect()->route('login')->withErrors(['username' => 'Zu viele falsche Codes. Bitte melde dich erneut an.']);
        }

        if (! $twoFactor->verifyLoginCode($user, (string) $request->input('code'))) {
            RateLimiter::hit($throttleKey, 300);

            ActivityLogger::log(
                'user.two_factor_failed',
                'Falscher Zwei-Faktor-Code für "'.$user->username.'".',
                'User',
                $user->id
            );

            throw ValidationException::withMessages(['code' => 'Der Code ist falsch oder abgelaufen.']);
        }

        RateLimiter::clear($throttleKey);
        $request->session()->forget(self::SESSION_KEY);

        if ($request->boolean('trust_device')) {
            $twoFactor->trustDevice($user);
        }

        return LoginController::completeLogin($request, $user);
    }

    public function resend(Request $request, TwoFactorAuthentication $twoFactor): RedirectResponse
    {
        $user = $this->pendingUser($request);

        if (! $user) {
            return redirect()->route('login');
        }

        if ($user->two_factor_method === TwoFactorAuthentication::METHOD_EMAIL) {
            $twoFactor->sendEmailCode($user);
        }

        return redirect()->route('two-factor.challenge')->with('success', 'Ein neuer Code wurde verschickt.');
    }

    private function pendingUser(Request $request): ?User
    {
        $pending = $request->session()->get(self::SESSION_KEY);

        if (! is_array($pending) || ($pending['expires_at'] ?? 0) < now()->getTimestamp()) {
            return null;
        }

        $user = User::query()->find($pending['user_id'] ?? null);

        return $user && $user->enabled ? $user : null;
    }
}
