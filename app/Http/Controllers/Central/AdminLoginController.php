<?php

namespace App\Http\Controllers\Central;

use App\Http\Controllers\Controller;
use App\Models\PlatformAdmin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Anmeldung der Plattform-Betreiber (Guard "platform").
 */
class AdminLoginController extends Controller
{
    private const MAX_LOGIN_ATTEMPTS = 5;

    public function create(): View
    {
        return view('central.admin.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ], [
            'email.required' => 'Bitte gib deine E-Mail-Adresse ein.',
            'email.email' => 'Bitte gib eine gültige E-Mail-Adresse ein.',
            'password.required' => 'Bitte gib dein Passwort ein.',
        ]);

        $throttleKey = 'platform-login:'.Str::lower($credentials['email']).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_LOGIN_ATTEMPTS)) {
            $minutes = (int) ceil(RateLimiter::availableIn($throttleKey) / 60);

            throw ValidationException::withMessages([
                'email' => 'Zu viele fehlgeschlagene Anmeldeversuche. Bitte versuche es in '.$minutes.' '.($minutes === 1 ? 'Minute' : 'Minuten').' erneut.',
            ]);
        }

        $admin = PlatformAdmin::query()->where('email', Str::lower($credentials['email']))->first();

        if (! $admin || ! Hash::check($credentials['password'], $admin->password)) {
            RateLimiter::hit($throttleKey, 300);

            throw ValidationException::withMessages([
                'email' => 'E-Mail-Adresse oder Passwort ist falsch.',
            ]);
        }

        RateLimiter::clear($throttleKey);

        Auth::guard('platform')->login($admin);
        $request->session()->regenerate();

        $admin->forceFill(['last_login_at' => now()])->save();

        return redirect()->intended(route('central.admin.tenants'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('platform')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('central.admin.login');
    }
}
