<?php

use App\Livewire\Admin\Users\Index as UsersIndex;
use App\Livewire\Auth\TwoFactorSetup;
use App\Mail\TwoFactorCodeMail;
use App\Models\Setting;
use App\Models\User;
use App\Services\TwoFactorAuthentication;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;

beforeEach(function () {
    Mail::fake();
    $this->freezeTime();
    $this->twoFactor = app(TwoFactorAuthentication::class);
});

function requireTwoFactor(bool $required = true): void
{
    Setting::current()->forceFill(['two_factor_required' => $required])->save();
}

function twoFactorStaff(?string $method = null): User
{
    $user = User::factory()->create(['username' => 'eva', 'password' => bcrypt('geheimes-passwort')]);

    if ($method) {
        app(TwoFactorAuthentication::class)->enable($user, $method, $method === 'app' ? 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ' : null);
    }

    return $user->fresh();
}

function currentTotp(): string
{
    return app(TwoFactorAuthentication::class)->totp('GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ', intdiv(now()->getTimestamp(), 30));
}

function postLogin(): TestResponse
{
    return test()->post('/login', ['username' => 'eva', 'password' => 'geheimes-passwort']);
}

it('calculates codes according to RFC 6238', function () {
    // Testvektor aus RFC 6238 (T = 59 s), letzte sechs Stellen.
    expect($this->twoFactor->totp('GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ', 1))->toBe('287082');
});

it('ignores two factor when the club has not enabled it', function () {
    twoFactorStaff('app');

    postLogin()->assertRedirect('/dashboard');
    $this->assertAuthenticated('web');
});

it('asks for the app code after the password and accepts each code once', function () {
    requireTwoFactor();
    $user = twoFactorStaff('app');

    postLogin()->assertRedirect(route('two-factor.challenge'));
    $this->assertGuest('web');

    $this->post(route('two-factor.challenge'), ['code' => '000000'])->assertSessionHasErrors('code');
    $this->assertGuest('web');

    $response = $this->post(route('two-factor.challenge'), ['code' => currentTotp(), 'trust_device' => '1']);
    $response->assertRedirect('/dashboard');
    $this->assertAuthenticatedAs($user, 'web');

    // Gemerktes Gerät: nächste Anmeldung ohne Code.
    $cookieName = 'vema_2fa_trusted_'.$user->id;
    $trustCookie = $response->getCookie($cookieName)->getValue();
    $this->post('/logout');

    $this->withCookie($cookieName, $trustCookie)->post('/login', ['username' => 'eva', 'password' => 'geheimes-passwort'])
        ->assertRedirect('/dashboard');
    $this->assertAuthenticatedAs($user, 'web');
});

it('rejects a reused app code', function () {
    requireTwoFactor();
    twoFactorStaff('app');
    $code = currentTotp();

    postLogin();
    $this->post(route('two-factor.challenge'), ['code' => $code])->assertRedirect('/dashboard');
    $this->post('/logout');

    postLogin();
    $this->post(route('two-factor.challenge'), ['code' => $code])->assertSessionHasErrors('code');
    $this->assertGuest('web');
});

it('sends the code by e-mail when the user chose e-mail', function () {
    requireTwoFactor();
    $user = twoFactorStaff('email');

    postLogin()->assertRedirect(route('two-factor.challenge'));

    $code = null;
    Mail::assertSent(TwoFactorCodeMail::class, function (TwoFactorCodeMail $mail) use (&$code, $user) {
        $code = $mail->code;

        return $mail->hasTo($user->email);
    });

    $this->post(route('two-factor.challenge'), ['code' => $code])->assertRedirect('/dashboard');
    $this->assertAuthenticatedAs($user, 'web');
});

it('accepts a recovery code exactly once', function () {
    requireTwoFactor();
    $user = twoFactorStaff();
    $codes = $this->twoFactor->enable($user, 'app', 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ');

    postLogin();
    $this->post(route('two-factor.challenge'), ['code' => strtoupper($codes[0])])->assertRedirect('/dashboard');
    $this->post('/logout');

    postLogin();
    $this->post(route('two-factor.challenge'), ['code' => $codes[0]])->assertSessionHasErrors('code');

    expect($this->twoFactor->remainingRecoveryCodes($user->fresh()))->toBe(7);
});

it('locks the challenge after too many wrong codes', function () {
    requireTwoFactor();
    twoFactorStaff('app');

    postLogin();

    foreach (range(1, 5) as $attempt) {
        $this->post(route('two-factor.challenge'), ['code' => '000000']);
    }

    $this->post(route('two-factor.challenge'), ['code' => currentTotp()])->assertRedirect(route('login'));
    $this->assertGuest('web');
});

it('forces staff without two factor to set it up first', function () {
    requireTwoFactor();
    $user = twoFactorStaff();

    postLogin()->assertRedirect('/dashboard');
    $this->get('/dashboard')->assertRedirect(route('two-factor.setup'));

    $component = Livewire::test(TwoFactorSetup::class)
        ->assertSet('step', 'choose')
        ->set('method', 'app')
        ->call('chooseMethod')
        ->assertSet('step', 'confirm');

    $secret = session('two_factor_setup_secret');

    $component->set('code', '123')
        ->call('confirm')
        ->assertHasErrors('code')
        ->set('code', $this->twoFactor->totp($secret, intdiv(now()->getTimestamp(), 30)))
        ->call('confirm')
        ->assertHasNoErrors()
        ->assertSet('step', 'codes')
        ->assertCount('recoveryCodes', 8);

    expect($user->fresh()->two_factor_method)->toBe('app')
        ->and($user->fresh()->two_factor_secret)->toBe($secret);

    $this->get('/dashboard')->assertOk();
});

it('lets admins require and reset two factor', function () {
    $admin = User::factory()->admin()->create();
    twoFactorStaff('app');
    $staff = User::query()->where('username', 'eva')->sole();
    $this->actingAs($admin, 'web');

    Livewire::test(UsersIndex::class)
        ->call('toggleTwoFactorRequired')
        ->assertRedirect(route('two-factor.setup'));

    expect(Setting::current()->two_factor_required)->toBeTrue();

    Livewire::test(UsersIndex::class)->call('resetTwoFactor', $staff->id);

    expect($staff->fresh()->two_factor_confirmed_at)->toBeNull()
        ->and($staff->fresh()->two_factor_secret)->toBeNull();
});
