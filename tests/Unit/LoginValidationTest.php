<?php

use App\Livewire\MemberAuth\Login;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;
use Tests\TestCase;

/*
 * Bewusst ohne Datenbank: Validierung und Drosselung greifen,
 * bevor eine Abfrage ausgeführt wird.
 */
uses(TestCase::class);

describe('member login', function () {
    it('requires an email address', function () {
        Livewire::test(Login::class)
            ->set('email', '')
            ->call('requestLoginLink')
            ->assertHasErrors(['email' => 'required'])
            ->assertSee('Bitte gib deine E-Mail-Adresse ein.')
            ->assertSet('statusMessage', null);
    });

    it('rejects invalid email addresses', function (string $invalidEmail) {
        Livewire::test(Login::class)
            ->set('email', $invalidEmail)
            ->call('requestLoginLink')
            ->assertHasErrors(['email' => 'email'])
            ->assertSee('Bitte gib eine gültige E-Mail-Adresse ein');
    })->with(['abc', 'a@b', 'name@', '@beispiel.at']);

    it('validates the email when leaving the field', function () {
        Livewire::test(Login::class)
            ->set('email', 'kein-mail')
            ->assertHasErrors(['email' => 'email']);
    });

    it('does not show an error for an untouched empty field', function () {
        Livewire::test(Login::class)
            ->set('email', '   ')
            ->assertHasNoErrors()
            ->assertSet('email', '');
    });

    it('throttles repeated link requests', function () {
        $throttleKey = 'member-login-link:max@beispiel.at|127.0.0.1';

        RateLimiter::clear($throttleKey);

        foreach (range(1, 3) as $attempt) {
            RateLimiter::hit($throttleKey, 600);
        }

        Livewire::test(Login::class)
            ->set('email', ' Max@Beispiel.at ')
            ->call('requestLoginLink')
            ->assertHasErrors('email')
            ->assertSee('Es wurden bereits mehrere Login-Links angefordert')
            ->assertSet('statusMessage', null);
    });
});

describe('internal login', function () {
    it('shows german messages for missing username and password', function () {
        $this->from('/login')
            ->post('/login', ['username' => '  ', 'password' => ''])
            ->assertRedirect('/login')
            ->assertSessionHasErrors([
                'username' => 'Bitte gib deinen Benutzernamen ein.',
                'password' => 'Bitte gib dein Passwort ein.',
            ]);
    });

    it('locks the login after too many failed attempts', function () {
        $throttleKey = 'login:admin|127.0.0.1';

        RateLimiter::clear($throttleKey);

        foreach (range(1, 5) as $attempt) {
            RateLimiter::hit($throttleKey, 300);
        }

        $this->from('/login')
            ->post('/login', ['username' => 'Admin', 'password' => 'falsch'])
            ->assertSessionHasErrors('username');

        expect(session('errors')->first('username'))
            ->toContain('Zu viele fehlgeschlagene Anmeldeversuche');
    });
});
