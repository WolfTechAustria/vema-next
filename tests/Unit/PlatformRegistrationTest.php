<?php

use App\Enums\Plan;
use App\Enums\TenantStatus;
use App\Livewire\Central\FindClub;
use App\Livewire\Central\Register;
use App\Mail\Central\ClubLinksMail;
use App\Mail\Central\TenantVerificationMail;
use App\Mail\Central\TenantWelcomeMail;
use App\Models\Setting;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantManager;
use App\Services\TenantProvisioner;
use App\Services\TenantRegistration;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

/*
 * Selbstregistrierung auf app.vemat.test (Modus "multi", SQLite-Dateien).
 */
uses(TestCase::class);

beforeEach(function () {
    bootMultiTenancyForTests();

    Mail::fake();
});

afterEach(function () {
    shutdownMultiTenancyForTests();
});

/**
 * Registriert einen Verein über das Formular und liefert den Link aus der Bestätigungsmail.
 */
function registerClubViaForm(string $slug = 'musikverein-musterdorf', string $plan = 'verein-plus'): string
{
    Livewire::withQueryParams(['paket' => $plan])
        ->test(Register::class)
        ->set('club_name', 'Musikverein Musterdorf')
        ->set('slug', $slug)
        ->set('contact_name', 'Maria Muster')
        ->set('contact_email', 'Obfrau@Musterdorf.at')
        ->set('accept_terms', true)
        ->call('register')
        ->assertHasNoErrors()
        ->assertSet('registeredEmail', 'Obfrau@Musterdorf.at');

    $verificationUrl = null;

    Mail::assertSent(TenantVerificationMail::class, function (TenantVerificationMail $mail) use (&$verificationUrl) {
        $verificationUrl = $mail->verificationUrl;

        return $mail->hasTo('obfrau@musterdorf.at');
    });

    return $verificationUrl;
}

describe('registration', function () {
    it('creates a pending club and sends a confirmation link', function () {
        $verificationUrl = registerClubViaForm();

        $tenant = Tenant::query()->where('slug', 'musikverein-musterdorf')->sole();

        expect($tenant->status)->toBe(TenantStatus::Pending)
            ->and($tenant->name)->toBe('Musikverein Musterdorf')
            ->and($tenant->contact_email)->toBe('obfrau@musterdorf.at')
            ->and($tenant->requested_plan)->toBe(Plan::VereinPlus)
            ->and($tenant->plan)->toBe(Plan::Starter)
            ->and($tenant->provisioned_at)->toBeNull()
            ->and($verificationUrl)->toContain('://app.vemat.test/registrieren/bestaetigen/');

        // Nur der Hash des Tokens liegt in der Datenbank.
        expect($verificationUrl)->not->toContain($tenant->verification_token);

        $this->get('http://musikverein-musterdorf.vemat.test/login')->assertForbidden();
    });

    it('shows the package chosen on the website', function (string $parameter) {
        $this->get('http://app.vemat.test/registrieren?'.$parameter.'=verein-plus')
            ->assertOk()
            ->assertSee('Gewähltes Paket: Verein Plus');
    })->with(['paket', 'plan']);

    it('suggests the address from the club name until it is edited', function () {
        Livewire::test(Register::class)
            ->set('club_name', 'Schützengilde Größing')
            ->assertSet('slug', 'schuetzengilde-groessing')
            ->set('slug', 'sg-groessing')
            ->set('club_name', 'Schützengilde Größing 1850')
            ->assertSet('slug', 'sg-groessing');
    });

    it('rejects invalid, reserved and taken addresses', function (string $slug, string $message) {
        createTenantWithDatabase('vergeben', 'Vergebener Verein');

        Livewire::test(Register::class)
            ->set('club_name', 'Neuer Verein')
            ->set('slug', $slug)
            ->set('contact_name', 'Max')
            ->set('contact_email', 'max@example.at')
            ->set('accept_terms', true)
            ->call('register')
            ->assertHasErrors('slug')
            ->assertSee($message);

        expect(Tenant::query()->count())->toBe(1);
    })->with([
        'reserved' => ['app', 'reserviert'],
        'taken' => ['vergeben', 'bereits vergeben'],
        'uppercase or dots' => ['mein.verein', 'Nur Kleinbuchstaben'],
        'leading dash' => ['-verein', 'Nur Kleinbuchstaben'],
        'too short' => ['ab', 'mindestens 3 Zeichen'],
    ]);

    it('requires accepting the terms', function () {
        Livewire::test(Register::class)
            ->set('club_name', 'Neuer Verein')
            ->set('slug', 'neuer-verein')
            ->set('contact_name', 'Max')
            ->set('contact_email', 'max@example.at')
            ->call('register')
            ->assertHasErrors('accept_terms');
    });

    it('silently ignores bots filling the honeypot', function () {
        Livewire::test(Register::class)
            ->set('club_name', 'Spam Verein')
            ->set('slug', 'spam-verein')
            ->set('contact_name', 'Bot')
            ->set('contact_email', 'bot@example.at')
            ->set('accept_terms', true)
            ->set('website', 'https://spam.example')
            ->call('register')
            ->assertSet('registeredEmail', 'bot@example.at');

        expect(Tenant::query()->count())->toBe(0);
        Mail::assertNothingSent();
    });
});

describe('confirmation and provisioning', function () {
    it('only shows a confirmation page on GET so link scanners do not provision', function () {
        $verificationUrl = registerClubViaForm();

        $this->get($verificationUrl)
            ->assertOk()
            ->assertSee('Verein jetzt einrichten');

        expect(Tenant::query()->sole()->provisioned_at)->toBeNull();
    });

    it('provisions the club, starts the trial and leads to setting the password', function () {
        $verificationUrl = registerClubViaForm();

        $response = $this->post($verificationUrl);

        $tenant = Tenant::query()->sole();

        expect($tenant->status)->toBe(TenantStatus::Active)
            ->and($tenant->isAccessible())->toBeTrue()
            ->and($tenant->isOnTrial())->toBeTrue()
            ->and($tenant->trial_ends_at->isSameDay(now()->addDays(30)))->toBeTrue()
            ->and($tenant->email_verified_at)->not->toBeNull()
            ->and($tenant->verification_token)->toBeNull()
            ->and(is_file($tenant->database))->toBeTrue()
            ->and(is_file($tenant->demoDatabase()))->toBeTrue();

        $response->assertRedirect();
        expect($response->headers->get('Location'))->toStartWith('http://musikverein-musterdorf.vemat.test/reset-password/');

        app(TenantManager::class)->runFor($tenant, function () {
            expect(Setting::current()->name)->toBe('Musikverein Musterdorf')
                ->and(Setting::current()->email)->toBe('obfrau@musterdorf.at');

            $admin = User::query()->sole();

            expect($admin->username)->toBe('obfrau@musterdorf.at')
                ->and($admin->hasRole('admin'))->toBeTrue();
        });

        Mail::assertSent(TenantWelcomeMail::class, fn (TenantWelcomeMail $mail) => $mail->hasTo('obfrau@musterdorf.at'));

        // Der Link funktioniert nur einmal.
        $this->post($verificationUrl)->assertNotFound();
    });

    it('lets the new admin set a password and log in to the club', function () {
        $passwordSetupUrl = $this->post(registerClubViaForm())->headers->get('Location');

        $this->get($passwordSetupUrl)->assertOk();

        parse_str((string) parse_url($passwordSetupUrl, PHP_URL_QUERY), $query);
        $token = basename((string) parse_url($passwordSetupUrl, PHP_URL_PATH));

        $this->post('http://musikverein-musterdorf.vemat.test/reset-password', [
            'token' => $token,
            'email' => $query['email'],
            'password' => 'Sicheres-Passwort-2026',
            'password_confirmation' => 'Sicheres-Passwort-2026',
        ])->assertRedirect(route('login'))->assertSessionHasNoErrors();

        $this->post('http://musikverein-musterdorf.vemat.test/login', [
            'username' => 'obfrau@musterdorf.at',
            'password' => 'Sicheres-Passwort-2026',
        ])->assertRedirect();

        $this->get('http://musikverein-musterdorf.vemat.test/dashboard')->assertOk();
    });

    it('rejects expired confirmation links', function () {
        $verificationUrl = registerClubViaForm();

        $this->travel(config('tenancy.verification_hours') + 1)->hours();

        $this->get($verificationUrl)->assertNotFound()->assertSee('abgelaufen');
        $this->post($verificationUrl)->assertNotFound();
    });

    it('prunes expired unconfirmed registrations and keeps the rest', function () {
        registerClubViaForm('alt-verein');
        createTenantWithDatabase('aktiver-verein', 'Aktiver Verein', ['created_at' => now()->subYear()]);

        $this->travel(config('tenancy.verification_hours') + 1)->hours();
        registerClubViaForm('frischer-verein');

        $this->artisan('tenants:prune-unverified')->assertSuccessful();

        expect(Tenant::query()->orderBy('slug')->pluck('slug')->all())->toBe(['aktiver-verein', 'frischer-verein']);
    });

    it('builds a database name from the address', function () {
        config(['database.connections.tenant.driver' => 'mysql']);

        expect(app(TenantProvisioner::class)->databaseNameFor('musikverein-musterdorf'))
            ->toBe('vema_t_musikverein_musterdorf');
    });
});

describe('find club', function () {
    beforeEach(function () {
        $this->club = createTenantWithDatabase('trachtenverein', 'Trachtenverein', ['contact_email' => 'obmann@tracht.at']);
    });

    it('forwards to the login of the club', function (string $address) {
        Livewire::test(FindClub::class)
            ->set('address', $address)
            ->call('openClub')
            ->assertRedirect('http://trachtenverein.vemat.test/login');
    })->with(['trachtenverein', ' Trachtenverein.vemat.test ', 'https://trachtenverein.vemat.test/login']);

    it('forwards members to the member login of their club', function () {
        $this->get('http://app.vemat.test/member/login')->assertRedirect('/login?ziel=mitglieder');

        Livewire::withQueryParams(['ziel' => 'mitglieder'])
            ->test(FindClub::class)
            ->assertSee('Mitglieder-Login')
            ->set('address', 'trachtenverein')
            ->call('openClub')
            ->assertRedirect('http://trachtenverein.vemat.test/member/login');
    });

    it('reports unknown and unconfirmed addresses', function () {
        app(TenantRegistration::class)->register([
            'club_name' => 'Unbestätigt',
            'slug' => 'unbestaetigt',
            'contact_name' => 'X',
            'contact_email' => 'x@example.at',
        ]);

        foreach (['gibt-es-nicht', 'unbestaetigt'] as $address) {
            Livewire::test(FindClub::class)
                ->set('address', $address)
                ->call('openClub')
                ->assertHasErrors('address')
                ->assertNoRedirect();
        }
    });

    it('mails the club links only to a known contact address without revealing it', function () {
        Livewire::test(FindClub::class)
            ->set('email', 'unbekannt@example.at')
            ->call('sendLinks')
            ->assertSee('Wenn diese E-Mail-Adresse');

        Mail::assertNotSent(ClubLinksMail::class);

        Livewire::test(FindClub::class)
            ->set('email', 'Obmann@Tracht.at')
            ->call('sendLinks')
            ->assertSee('Wenn diese E-Mail-Adresse');

        Mail::assertSent(ClubLinksMail::class, fn (ClubLinksMail $mail) => $mail->hasTo('Obmann@Tracht.at')
            && $mail->tenants->pluck('slug')->all() === ['trachtenverein']);
    });
});
