<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;

class ForgotPasswordController extends Controller
{
    public function create()
    {
        return view('auth.forgot-password');
    }

    public function store(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email:rfc,filter', 'max:255'],
        ], [
            'email.required' => 'Bitte gib deine E-Mail-Adresse ein.',
            'email.email' => 'Bitte gib eine gültige E-Mail-Adresse ein (z. B. name@beispiel.at).',
            'email.max' => 'Die E-Mail-Adresse darf höchstens 255 Zeichen lang sein.',
        ]);

        // Absichtlich keine Rückmeldung, ob die E-Mail existiert
        // (verhindert das Ausspähen gültiger Benutzer-E-Mails).
        Password::sendResetLink($request->only('email'));

        return back()->with(
            'success',
            'Falls diese E-Mail-Adresse einem Benutzer zugeordnet ist, wurde ein Link zum Zurücksetzen des Passworts verschickt.'
        );
    }
}
