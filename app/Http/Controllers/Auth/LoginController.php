<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    /**
     * Fehlversuche je Benutzername und IP, bevor für fünf Minuten gesperrt wird.
     */
    private const MAX_LOGIN_ATTEMPTS = 5;

    public function create()
    {
        return view('auth.login');
    }

    public function store(Request $request)
    {
        $request->merge([
            'username' => trim((string) $request->input('username')),
        ]);

        $credentials = $request->validate([
            'username' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
        ], [
            'username.required' => 'Bitte gib deinen Benutzernamen ein.',
            'username.max' => 'Der Benutzername darf höchstens 255 Zeichen lang sein.',
            'password.required' => 'Bitte gib dein Passwort ein.',
        ]);

        $throttleKey = 'login:'.Str::lower($credentials['username']).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_LOGIN_ATTEMPTS)) {
            $minutes = (int) ceil(RateLimiter::availableIn($throttleKey) / 60);

            throw ValidationException::withMessages([
                'username' => 'Zu viele fehlgeschlagene Anmeldeversuche. Bitte versuche es in '.$minutes.' '.($minutes === 1 ? 'Minute' : 'Minuten').' erneut.',
            ]);
        }

        $user = User::where('username', $credentials['username'])
            ->first();

        if (
            ! $user ||
            ! $user->enabled ||
            ! Hash::check($credentials['password'], $user->password)
        ) {
            RateLimiter::hit($throttleKey, 300);

            ActivityLogger::log(
                'user.login_failed',
                'Fehlgeschlagener Anmeldeversuch für Benutzername "'.$credentials['username'].'".'
            );

            throw ValidationException::withMessages([
                'username' => 'Benutzername oder Passwort ist falsch.',
            ]);
        }

        RateLimiter::clear($throttleKey);

        Auth::login($user);

        $request->session()->regenerate();

        ActivityLogger::log(
            'user.login',
            'Benutzer "'.$user->username.'" hat sich angemeldet.',
            'User',
            $user->id
        );

        return redirect()->intended('/dashboard');
    }

    public function destroy(Request $request)
    {
        $user = Auth::user();

        if ($user) {
            // Vor dem Logout protokollieren, solange der Guard den
            // Benutzer noch kennt (ActivityLogger ermittelt den Akteur
            // selbst über den aktiven Guard).
            ActivityLogger::log(
                'user.logout',
                'Benutzer "'.$user->username.'" hat sich abgemeldet.',
                'User',
                $user->id
            );
        }

        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }
}
