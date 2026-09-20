<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    public function create()
    {
        return view('auth.login');
    }

    public function store(Request $request)
    {
        $credentials = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('username', $credentials['username'])
            ->first();

        if (
            !$user ||
            !$user->enabled ||
            !Hash::check($credentials['password'], $user->password)
        ) {
            ActivityLogger::log(
                'user.login_failed',
                'Fehlgeschlagener Anmeldeversuch für Benutzername "' . $credentials['username'] . '".'
            );

            throw ValidationException::withMessages([
                'username' => 'Benutzername oder Passwort ist falsch.',
            ]);
        }

        Auth::login($user);

        $request->session()->regenerate();

        ActivityLogger::log(
            'user.login',
            'Benutzer "' . $user->username . '" hat sich angemeldet.',
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
                'Benutzer "' . $user->username . '" hat sich abgemeldet.',
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
