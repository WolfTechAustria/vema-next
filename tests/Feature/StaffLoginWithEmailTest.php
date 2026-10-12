<?php

use App\Models\User;
use Illuminate\Testing\TestResponse;

function attemptStaffLogin(string $login, string $password = 'password'): TestResponse
{
    return test()->post('/login', ['username' => $login, 'password' => $password]);
}

it('still signs in with the username', function () {
    $user = User::factory()->create(['username' => 'kassier', 'email' => 'kassier@example.com']);

    attemptStaffLogin('kassier')->assertRedirect('/dashboard');

    $this->assertAuthenticatedAs($user);
});

it('signs in with the e-mail address regardless of case', function () {
    $user = User::factory()->create(['username' => 'kassier', 'email' => 'kassier@example.com']);

    attemptStaffLogin('Kassier@Example.com')->assertRedirect('/dashboard');

    $this->assertAuthenticatedAs($user);
});

it('picks the account whose password matches when an address is shared', function () {
    User::factory()->create(['username' => 'obmann', 'email' => 'max@example.com', 'password' => bcrypt('erstes-passwort')]);
    $kassier = User::factory()->create(['username' => 'kassier', 'email' => 'max@example.com', 'password' => bcrypt('zweites-passwort')]);

    attemptStaffLogin('max@example.com', 'zweites-passwort')->assertRedirect('/dashboard');

    $this->assertAuthenticatedAs($kassier);
});

it('rejects wrong passwords and disabled accounts', function (array $attributes, string $password) {
    User::factory()->create(['username' => 'kassier', 'email' => 'kassier@example.com', ...$attributes]);

    attemptStaffLogin('kassier@example.com', $password)
        ->assertSessionHasErrors(['username' => 'Benutzername/E-Mail oder Passwort ist falsch.']);

    $this->assertGuest();
})->with([
    'wrong password' => [[], 'falsch'],
    'disabled' => [['enabled' => false], 'password'],
]);

it('labels the login field for username or e-mail', function () {
    $this->get('/login')->assertSee('Benutzername oder E-Mail');
});
