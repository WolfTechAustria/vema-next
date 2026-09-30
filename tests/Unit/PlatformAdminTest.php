<?php

use App\Enums\Plan;
use App\Enums\TenantStatus;
use App\Livewire\Central\Admin\TenantDetail;
use App\Livewire\Central\Admin\Tenants;
use App\Mail\Central\TenantReminderMail;
use App\Models\PlatformAdmin;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    bootMultiTenancyForTests();

    Mail::fake();

    $this->admin = PlatformAdmin::create([
        'name' => 'Betreiber',
        'email' => 'office@vemat.test',
        'password' => 'ein-sehr-sicheres-passwort',
    ]);

    $this->club = createTenantWithDatabase('musikverein', 'Musikverein', [
        'plan' => Plan::Starter,
        'trial_ends_at' => now()->addDays(20),
        'contact_email' => 'obfrau@musikverein.at',
    ]);
});

afterEach(function () {
    shutdownMultiTenancyForTests();
});

describe('platform admin', function () {
    it('requires a platform login', function () {
        $this->get('http://app.vemat.test/admin')
            ->assertRedirect(route('central.admin.login'));

        $this->get('http://app.vemat.test/admin/login')
            ->assertOk()
            ->assertSee('Plattform-Admin');

        // Nur auf dem Plattform-Host.
        $this->get('http://musikverein.vemat.test/admin/login')->assertNotFound();
    });

    it('logs in with correct credentials only', function () {
        $this->post('http://app.vemat.test/admin/login', [
            'email' => 'office@vemat.test',
            'password' => 'falsch',
        ])->assertSessionHasErrors('email');

        $this->assertGuest('platform');

        $this->post('http://app.vemat.test/admin/login', [
            'email' => 'Office@vemat.test',
            'password' => 'ein-sehr-sicheres-passwort',
        ])->assertRedirect(route('central.admin.tenants'));

        $this->assertAuthenticatedAs($this->admin, 'platform');

        $this->get('http://app.vemat.test/admin')
            ->assertOk()
            ->assertSee('Musikverein')
            ->assertSee('musikverein.vemat.test');
    });

    it('does not let club staff into the platform admin', function () {
        // Staff-Login eines Vereins ist kein Plattform-Login.
        $this->actingAs(User::factory()->make(['id' => 1]), 'web');

        $this->get('http://app.vemat.test/admin')
            ->assertRedirect(route('central.admin.login'));
    });

    it('lists and filters clubs', function () {
        createTenantWithDatabase('gesperrt', 'Gesperrter Verein', ['status' => TenantStatus::Suspended]);

        Livewire::actingAs($this->admin, 'platform')
            ->test(Tenants::class)
            ->assertSee('Musikverein')
            ->assertSee('Gesperrter Verein')
            ->set('status', 'suspended')
            ->assertDontSee('musikverein.vemat.test')
            ->assertSee('Gesperrter Verein')
            ->set('status', '')
            ->set('search', 'musik')
            ->assertDontSee('Gesperrter Verein');
    });

    it('changes plan, license and trial', function () {
        Livewire::actingAs($this->admin, 'platform')
            ->test(TenantDetail::class, ['tenant' => $this->club])
            ->assertSee('Aktive Mitglieder / Vorstandszugänge')
            ->set('plan', 'verein')
            ->set('license_valid_until', now()->addYear()->toDateString())
            ->set('trial_ends_at', '')
            ->call('save')
            ->assertHasNoErrors();

        $club = $this->club->fresh();

        expect($club->plan)->toBe(Plan::Verein)
            ->and($club->license_valid_until->isSameDay(now()->addYear()))->toBeTrue()
            ->and($club->trial_ends_at)->toBeNull()
            ->and($club->effectivePlan())->toBe(Plan::Verein);

        Livewire::actingAs($this->admin, 'platform')
            ->test(TenantDetail::class, ['tenant' => $club])
            ->call('extendTrial', 14);

        expect($club->fresh()->trial_ends_at->isSameDay(now()->addDays(14)))->toBeTrue();
    });

    it('suspends and reactivates a club', function () {
        Livewire::actingAs($this->admin, 'platform')
            ->test(TenantDetail::class, ['tenant' => $this->club])
            ->call('suspend');

        expect($this->club->fresh()->status)->toBe(TenantStatus::Suspended);
        $this->get('http://musikverein.vemat.test/login')->assertForbidden();

        Livewire::actingAs($this->admin, 'platform')
            ->test(TenantDetail::class, ['tenant' => $this->club->fresh()])
            ->call('reactivate');

        expect($this->club->fresh()->status)->toBe(TenantStatus::Active);

        // actingAs(..., 'platform') hat den Standard-Guard des Tests umgestellt.
        auth()->forgetGuards();
        auth()->shouldUse('web');

        $this->get('http://musikverein.vemat.test/login')->assertOk();
    });

    it('creates platform admins from the command line', function () {
        $this->artisan('platform:admin-create', ['email' => 'Chef@vemat.test', '--name' => 'Chef'])
            ->expectsQuestion('Passwort (mind. 12 Zeichen)', 'noch-ein-langes-passwort')
            ->expectsQuestion('Passwort wiederholen', 'noch-ein-langes-passwort')
            ->assertSuccessful();

        $admin = PlatformAdmin::query()->where('email', 'chef@vemat.test')->sole();

        expect($admin->name)->toBe('Chef')
            ->and(Hash::check('noch-ein-langes-passwort', $admin->password))->toBeTrue();

        $this->artisan('platform:admin-create', ['email' => 'kurz@vemat.test'])
            ->expectsQuestion('Passwort (mind. 12 Zeichen)', 'zu-kurz')
            ->assertFailed();
    });
});

describe('reminders', function () {
    it('reminds once per stage before the trial ends', function () {
        $this->club->update(['trial_ends_at' => now()->addDays(6)]);

        $this->artisan('tenants:send-reminders')->assertSuccessful();
        $this->artisan('tenants:send-reminders')->assertSuccessful();

        Mail::assertSent(TenantReminderMail::class, 1);
        Mail::assertSent(TenantReminderMail::class, fn (TenantReminderMail $mail) => $mail->reminder === 'trial-7'
            && $mail->hasTo('obfrau@musikverein.at'));

        $this->travel(5)->days();
        $this->artisan('tenants:send-reminders')->assertSuccessful();

        Mail::assertSent(TenantReminderMail::class, fn (TenantReminderMail $mail) => $mail->reminder === 'trial-1');

        $this->travel(2)->days();
        $this->artisan('tenants:send-reminders')->assertSuccessful();

        Mail::assertSent(TenantReminderMail::class, fn (TenantReminderMail $mail) => $mail->reminder === 'trial-ended');
        Mail::assertSent(TenantReminderMail::class, 3);
    });

    it('reminds before the license expires', function () {
        $this->club->update([
            'trial_ends_at' => null,
            'license_valid_until' => now()->addDays(20)->toDateString(),
        ]);

        $this->artisan('tenants:send-reminders')->assertSuccessful();

        Mail::assertSent(TenantReminderMail::class, fn (TenantReminderMail $mail) => $mail->reminder === 'license-30');

        // Neue Frist im Admin → Erinnerungen wieder möglich.
        Livewire::actingAs($this->admin, 'platform')
            ->test(TenantDetail::class, ['tenant' => $this->club->fresh()])
            ->set('license_valid_until', now()->addDays(5)->toDateString())
            ->call('save');

        $this->artisan('tenants:send-reminders')->assertSuccessful();

        Mail::assertSent(TenantReminderMail::class, fn (TenantReminderMail $mail) => $mail->reminder === 'license-7');
    });

    it('does not remind clubs without deadlines', function () {
        $this->club->update(['trial_ends_at' => null, 'license_valid_until' => null]);

        $this->artisan('tenants:send-reminders')->assertSuccessful();

        Mail::assertNothingSent();
    });
});
