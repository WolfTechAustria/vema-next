<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rules\Password as PasswordRule;

class ResetPasswordController extends Controller
{
    public function create(Request $request, string $token)
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => $request->query('email', ''),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)],
        ], [
            'token.required' => 'Der Link ist unvollständig. Bitte fordere einen neuen Link an.',
            'email.required' => 'Bitte gib deine E-Mail-Adresse ein.',
            'email.email' => 'Bitte gib eine gültige E-Mail-Adresse ein.',
            'password.required' => 'Bitte gib ein neues Passwort ein.',
            'password.confirmed' => 'Die beiden Passwörter stimmen nicht überein.',
            'password.min' => 'Das Passwort muss mindestens 8 Zeichen lang sein.',
        ]);

        $status = Password::reset(
            $validated,
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return back()
                ->withErrors(['email' => match ($status) {
                    Password::INVALID_TOKEN => 'Dieser Link ist ungültig oder abgelaufen. Bitte fordere einen neuen Link an.',
                    Password::INVALID_USER => 'Zu dieser E-Mail-Adresse wurde kein Benutzer gefunden.',
                    Password::RESET_THROTTLED => 'Bitte warte kurz, bevor du es erneut versuchst.',
                    default => 'Das Passwort konnte nicht zurückgesetzt werden.',
                }])
                ->withInput($request->only('email'));
        }

        return redirect()
            ->route('login')
            ->with('success', 'Dein Passwort wurde geändert. Du kannst dich jetzt anmelden.');
    }
}
