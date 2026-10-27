<?php

use App\Enums\Plan;
use App\Enums\TenantStatus;
use App\Livewire\Central\Admin\Account;
use App\Livewire\Central\Admin\TenantDetail;
use App\Livewire\Central\Admin\Tenants;
use App\Mail\Central\TenantAccessMail;
use App\Mail\Central\TenantVerificationMail;
use App\Mail\Central\TenantWelcomeMail;
use App\Models\PlatformAdmin;
use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Models\User;
use App\Services\TenantManager;
use App\Services\TenantRegistration;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/*
 * Aktionen im Plattform-Admin (app.vemat.test/admin).
 */
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
        'contact_email' => 'obfrau@musikverein.at',
    ]);

    app(TenantManager::class)->runFor($this->club, function () {
        User::factory()->create(['username' => 'obfrau', 'email' => 'obfrau@musikverein.at'])
            ->assignRole(Role::findOrCreate('admin', 'web'));
    });
});

afterEach(function () {
    shutdownMultiTenancyForTests();
});

function tenantDetail(Tenant $tenant): Testable
{
    return Livewire::actingAs(test()->admin, 'platform')->test(TenantDetail::class, ['tenant' => $tenant]);
}

describe('club details', function () {
    it('edits master data and notes and records the changes', function () {
        tenantDetail($this->club)
            ->set('name', 'Musikverein Neu')
            ->set('contact_email', 'Kassier@Musikverein.at')
            ->set('admin_notes', 'Zahlt per Rechnung')
            ->call('saveDetails')
            ->assertHasNoErrors()
            ->assertSee('Stammdaten geändert')
            ->assertSee('Musikverein → Musikverein Neu');

        $club = $this->club->fresh();

        expect($club->name)->toBe('Musikverein Neu')
            ->and($club->contact_email)->toBe('kassier@musikverein.at')
            ->and($club->admin_notes)->toBe('Zahlt per Rechnung')
            ->and($club->events()->sole()->platform_admin_id)->toBe($this->admin->id);
    });

    it('ends the trial early', function () {
        $this->club->update(['trial_ends_at' => now()->addDays(10)]);

        tenantDetail($this->club->fresh())->call('endTrial');

        expect($this->club->fresh()->isOnTrial())->toBeFalse()
            ->and($this->club->fresh()->effectivePlan())->toBe(Plan::Starter);
    });

    it('warns when usage exceeds the plan limits', function () {
        app(TenantManager::class)->runFor($this->club, function () {
            User::factory()->count(3)->create();
        });

        tenantDetail($this->club)->assertSee('Limit aktuell überschritten');
    });

    it('adds and removes own domains', function () {
        createTenantWithDatabase('anderer', 'Anderer Verein');
        TenantDomain::create(['tenant_id' => Tenant::query()->where('slug', 'anderer')->value('id'), 'domain' => 'www.vergeben.at']);

        tenantDetail($this->club)
            ->set('newDomain', 'kein domain')->call('addDomain')->assertHasErrors('newDomain')
            ->set('newDomain', 'x.vemat.test')->call('addDomain')->assertHasErrors('newDomain')
            ->set('newDomain', 'www.vergeben.at')->call('addDomain')->assertHasErrors('newDomain')
            ->set('newDomain', 'WWW.Musikverein.at')
            ->set('newDomainPurpose', 'homepage')
            ->call('addDomain')
            ->assertHasNoErrors();

        $domain = $this->club->domains()->sole();

        expect($domain->domain)->toBe('www.musikverein.at')
            ->and($domain->purpose)->toBe('homepage');

        tenantDetail($this->club)->call('removeDomain', $domain->id);

        expect($this->club->domains()->count())->toBe(0);
    });
});

describe('access', function () {
    it('mails a new password setup link to the club admin', function () {
        tenantDetail($this->club)->call('sendAccessLink');

        Mail::assertSent(TenantAccessMail::class, fn (TenantAccessMail $mail) => $mail->hasTo('obfrau@musikverein.at')
            && $mail->username === 'obfrau'
            && str_starts_with($mail->passwordSetupUrl, 'http://musikverein.vemat.test/reset-password/'));
    });

    it('resends the confirmation link and restarts the deadline', function () {
        $pending = Tenant::factory()->pending()->create([
            'email_verified_at' => null,
            'provisioned_at' => null,
            'created_at' => now()->subDays(5),
        ]);

        tenantDetail($pending)->assertSee('abgelaufen')->call('resendVerification');

        Mail::assertSent(TenantVerificationMail::class, function (TenantVerificationMail $mail) use ($pending) {
            return $mail->hasTo($pending->contact_email)
                && app(TenantRegistration::class)->findPendingByToken(basename($mail->verificationUrl))?->is($pending);
        });
    });

    it('confirms and provisions a registration manually', function () {
        $pending = app(TenantRegistration::class)->register([
            'club_name' => 'Schützenverein',
            'slug' => 'schuetzen',
            'contact_name' => 'Max Muster',
            'contact_email' => 'max@schuetzen.at',
        ]);

        tenantDetail($pending)->call('verifyManually');

        $pending->refresh();

        expect($pending->status)->toBe(TenantStatus::Active)
            ->and($pending->isOnTrial())->toBeTrue();

        Mail::assertSent(TenantWelcomeMail::class, fn (TenantWelcomeMail $mail) => $mail->hasTo('max@schuetzen.at'));
    });
});

describe('support login', function () {
    it('logs the platform admin into the club with a one-time link', function () {
        // Der Array-Cache der Tests überlebt den Vereinswechsel nicht — die
        // Einmal-Sperre braucht einen echten Store wie im Betrieb.
        config(['cache.default' => 'file', 'cache.stores.file.path' => $this->tenancyDirectory.'/cache']);
        $redirect = $this->actingAs($this->admin, 'platform')
            ->post('http://app.vemat.test/admin/vereine/'.$this->club->id.'/anmelden');

        $supportUrl = $redirect->headers->get('Location');

        expect($supportUrl)->toStartWith('http://musikverein.vemat.test/support-login?');

        auth()->forgetGuards();
        auth()->shouldUse('web');

        $this->get($supportUrl)
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('support_login', 'Betreiber');

        expect(auth('web')->user()->username)->toBe('obfrau');

        $this->get('http://musikverein.vemat.test/dashboard')
            ->assertOk()
            ->assertSee('Support-Anmeldung');

        expect($this->club->events()->sole()->action)->toBe('Als Verein angemeldet');

        // Nur einmal verwendbar.
        auth()->forgetGuards();
        $this->get($supportUrl)->assertForbidden();
    });

    it('rejects the link on another club and when tampered with', function () {
        createTenantWithDatabase('anderer', 'Anderer Verein');

        $supportUrl = $this->actingAs($this->admin, 'platform')
            ->post('http://app.vemat.test/admin/vereine/'.$this->club->id.'/anmelden')
            ->headers->get('Location');

        auth()->forgetGuards();
        auth()->shouldUse('web');

        $this->get(str_replace('musikverein.vemat.test', 'anderer.vemat.test', $supportUrl))->assertForbidden();
        $this->get(str_replace('admin='.$this->admin->id, 'admin=999', $supportUrl))->assertForbidden();

        $this->travel(3)->minutes();
        $this->get($supportUrl)->assertForbidden();

        $this->assertGuest('web');
    });

    it('is only available to platform admins', function () {
        $this->post('http://app.vemat.test/admin/vereine/'.$this->club->id.'/anmelden')
            ->assertRedirect(route('central.admin.login'));
    });
});

describe('deleting', function () {
    it('requires typing the address', function () {
        tenantDetail($this->club)
            ->set('deleteConfirmation', 'falsch')
            ->call('deleteTenant')
            ->assertHasErrors('deleteConfirmation');

        expect($this->club->fresh())->not->toBeNull();
    });

    it('deletes the club with its database, files and domains', function () {
        File::ensureDirectoryExists($this->club->storagePath('private'));
        $this->club->domains()->create(['domain' => 'www.musikverein.at']);
        $this->club->recordEvent('Test');

        tenantDetail($this->club)
            ->set('deleteConfirmation', 'musikverein')
            ->call('deleteTenant')
            ->assertRedirect(route('central.admin.tenants'));

        expect(Tenant::query()->find($this->club->id))->toBeNull()
            ->and(is_file($this->club->database))->toBeFalse()
            ->and(is_dir($this->club->storagePath()))->toBeFalse()
            ->and(TenantDomain::query()->count())->toBe(0);
    });
});

describe('overview and account', function () {
    it('filters clubs that need attention', function () {
        $this->club->update(['trial_ends_at' => now()->addDays(3)]);
        createTenantWithDatabase('abgelaufen', 'Abgelaufener Verein', ['license_valid_until' => now()->subDay()]);

        Livewire::actingAs($this->admin, 'platform')
            ->test(Tenants::class)
            ->set('focus', 'trial-ending')
            ->assertSee('musikverein.vemat.test')
            ->assertDontSee('Abgelaufener Verein')
            ->set('focus', 'license-expired')
            ->assertSee('Abgelaufener Verein')
            ->assertDontSee('musikverein.vemat.test');
    });

    it('changes the own password only with the current one', function () {
        Livewire::actingAs($this->admin, 'platform')
            ->test(Account::class)
            ->set('current_password', 'falsch')
            ->set('password', 'neues-langes-passwort')
            ->set('password_confirmation', 'neues-langes-passwort')
            ->call('changePassword')
            ->assertHasErrors('current_password')
            ->set('current_password', 'ein-sehr-sicheres-passwort')
            ->call('changePassword')
            ->assertHasNoErrors();

        expect(Hash::check('neues-langes-passwort', $this->admin->fresh()->password))->toBeTrue();
    });
});
