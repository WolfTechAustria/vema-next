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
            'username.required' => 'Bitte gib deinen Benutzernamen oder deine E-Mail-Adresse ein.',
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

        $user = $this->resolveUser($credentials['username'], $credentials['password']);

        if (! $user) {
            RateLimiter::hit($throttleKey, 300);

            ActivityLogger::log(
                'user.login_failed',
                'Fehlgeschlagener Anmeldeversuch für "'.$credentials['username'].'".'
            );

            throw ValidationException::withMessages([
                'username' => 'Benutzername/E-Mail oder Passwort ist falsch.',
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

    /**
     * Anmeldung mit Benutzername oder E-Mail-Adresse. Der Benutzername hat
     * Vorrang; E-Mail-Adressen sind nicht eindeutig (z. B. zwei Zugänge
     * einer Person) — dann gilt der aktive Zugang, dessen Passwort passt.
     */
    private function resolveUser(string $login, string $password): ?User
    {
        $user = User::query()->where('username', $login)->first();

        if ($user) {
            return $this->acceptsPassword($user, $password) ? $user : null;
        }

        if (! str_contains($login, '@')) {
            return null;
        }

        return User::query()
            ->whereRaw('LOWER(email) = ?', [Str::lower($login)])
            ->get()
            ->first(fn (User $candidate) => $this->acceptsPassword($candidate, $password));
    }

    private function acceptsPassword(User $user, string $password): bool
    {
        return $user->enabled && Hash::check($password, $user->password);
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
