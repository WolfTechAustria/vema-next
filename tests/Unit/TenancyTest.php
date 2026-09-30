<?php

use App\Enums\TenantStatus;
use App\Http\Middleware\IdentifyTenant;
use App\Models\Setting;
use App\Services\ClubBranding;
use App\Services\DemoMode;
use App\Services\TenantManager;
use Illuminate\Console\Command;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/*
 * Ohne RefreshDatabase: Die Tests bauen die App im Modus "multi" neu auf und
 * arbeiten mit eigenen SQLite-Dateien (zentral + je Verein).
 */
uses(TestCase::class);

describe('single mode', function () {
    it('registers no platform routes and does not switch databases', function () {
        setUpCashBookSchema();

        expect(config('tenancy.mode'))->toBe('single')
            ->and(Route::has('central.home'))->toBeFalse();

        $this->get('/login')->assertOk();

        expect(DB::getDefaultConnection())->toBe('sqlite')
            ->and(app(TenantManager::class)->current())->toBeNull();
    });
});

describe('multi mode', function () {
    beforeEach(function () {
        bootMultiTenancyForTests();

        $this->clubA = createTenantWithDatabase('verein-a', 'Schützenverein A');
        $this->clubB = createTenantWithDatabase('verein-b', 'Musikverein B');
    });

    afterEach(function () {
        shutdownMultiTenancyForTests();
    });

    it('works against the central database until a club is identified', function () {
        expect(DB::getDefaultConnection())->toBe('landlord')
            ->and(config('session.connection'))->toBe('landlord');
    });

    it('serves only platform pages on the platform host', function () {
        $this->get('http://app.vemat.test/')->assertRedirect('/login');
        $this->get('http://app.vemat.test/login')->assertOk()->assertSee('Zu deinem Verein');
        $this->get('http://app.vemat.test/registrieren')->assertOk()->assertSee('Verein registrieren');

        // Vereins-Routen gibt es auf dem Plattform-Host nicht …
        $this->get('http://app.vemat.test/dashboard')->assertNotFound();
        $this->get('http://app.vemat.test/member/login')->assertRedirect('/login?ziel=mitglieder');

        // Livewire-Requests (Eingaben im Formular) müssen durchgehen.
        $livewireUpdateUrl = str_replace(url('/'), 'http://app.vemat.test', route('default-livewire.update'));
        // Ohne X-Livewire-Header oder mit leerer Payload antwortet Livewire selbst
        // mit 404 — daher ein formal vollständiger (ungültiger) Request, der erst
        // in Livewire scheitert.
        $livewireResponse = $this->withHeader('X-Livewire', 'true')->postJson($livewireUpdateUrl, [
            'components' => [['snapshot' => '{}', 'updates' => [], 'calls' => []]],
        ]);
        expect($livewireResponse->getStatusCode())->not->toBe(404);

        // … und Plattform-Routen nicht bei den Vereinen.
        $this->get('http://verein-a.vemat.test/registrieren')->assertNotFound();
        $this->get('http://verein-a.vemat.test/login')->assertOk()->assertDontSee('Zu deinem Verein');
    });

    it('returns 404 for unknown hosts', function () {
        $this->get('http://unbekannt.vemat.test/login')->assertNotFound();
        $this->get('http://anderer-host.test/login')->assertNotFound();
    });

    it('shows each club its own data', function () {
        $this->get('http://verein-a.vemat.test/login')
            ->assertOk()
            ->assertSee('Schützenverein A')
            ->assertDontSee('Musikverein B');

        $this->get('http://verein-b.vemat.test/login')
            ->assertOk()
            ->assertSee('Musikverein B')
            ->assertDontSee('Schützenverein A');
    });

    it('resolves clubs by their own domain', function () {
        $this->clubB->domains()->create(['domain' => 'vema.musikverein-b.at']);

        $this->get('http://vema.musikverein-b.at/login')->assertOk()->assertSee('Musikverein B');
    });

    it('blocks clubs that are not activated or whose license expired', function (array $attributes, string $message) {
        createTenantWithDatabase('verein-c', 'Trachtenverein C', $attributes);

        $this->get('http://verein-c.vemat.test/login')
            ->assertForbidden()
            ->assertSee($message);
    })->with([
        'pending' => [['status' => TenantStatus::Pending], 'sobald die E-Mail-Adresse bestätigt ist'],
        'suspended' => [['status' => TenantStatus::Suspended], 'gesperrt'],
        'license expired' => [['license_valid_until' => now()->subDay()], 'Lizenz dieses Vereins ist abgelaufen'],
    ]);

    it('accepts a license on its last day', function () {
        createTenantWithDatabase('verein-c', 'Trachtenverein C', ['license_valid_until' => today()]);

        $this->get('http://verein-c.vemat.test/login')->assertOk();
    });

    it('does not let a session of one club log in at another club', function () {
        $sessionGuardKey = Auth::guard('web')->getName();

        // Gleiche Benutzer-ID existiert in beiden Vereinsdatenbanken.
        $this->withSession([IdentifyTenant::SESSION_KEY => $this->clubA->id, $sessionGuardKey => 1])
            ->get('http://verein-b.vemat.test/dashboard')
            ->assertRedirect(route('login'));

        $this->withSession([IdentifyTenant::SESSION_KEY => $this->clubB->id, $sessionGuardKey => 1])
            ->get('http://verein-b.vemat.test/dashboard')
            ->assertOk();
    });

    it('keeps files of each club apart', function () {
        app(TenantManager::class)->runFor($this->clubA, function () {
            app(ClubBranding::class)->store('logo', UploadedFile::fake()->image('logo.png'));
        });

        expect(File::exists($this->clubA->storagePath('branding/logo.png')))->toBeTrue();

        $this->get('http://verein-a.vemat.test/branding/logo')->assertOk();
        $this->get('http://verein-b.vemat.test/branding/logo')->assertNotFound();
    });

    it('restores the central database after running code for a club', function () {
        $tenantManager = app(TenantManager::class);
        $landlordLocalRoot = config('filesystems.disks.local.root');

        $result = $tenantManager->runFor($this->clubA, fn () => [
            'connection' => DB::getDefaultConnection(),
            'club' => Setting::current()->name,
            'localRoot' => Storage::disk('local')->path(''),
            'loginUrl' => route('login'),
            'demoLiveConnection' => app(DemoMode::class)->liveConnection(),
            'demoDatabase' => config('database.connections.demo.database'),
        ]);

        expect($result['connection'])->toBe('tenant')
            ->and($result['club'])->toBe('Schützenverein A')
            ->and($result['localRoot'])->toStartWith($this->clubA->storagePath('private'))
            ->and($result['loginUrl'])->toBe('http://verein-a.vemat.test/login')
            ->and($result['demoLiveConnection'])->toBe('tenant')
            ->and($result['demoDatabase'])->toBe($this->clubA->demoDatabase());

        expect(DB::getDefaultConnection())->toBe('landlord')
            ->and($tenantManager->current())->toBeNull()
            ->and(config('filesystems.disks.local.root'))->toBe($landlordLocalRoot);
    });

    it('runs artisan commands for every accessible club', function () {
        createTenantWithDatabase('verein-c', 'Trachtenverein C', ['status' => TenantStatus::Pending]);

        Artisan::registerCommand(new class extends Command
        {
            protected $signature = 'test:club-name';

            public function handle(): void
            {
                $this->line('Verein: '.Setting::current()->name);
            }
        });

        $this->artisan('tenants:run', ['artisanCommand' => 'test:club-name'])
            ->expectsOutputToContain('Verein: Schützenverein A')
            ->expectsOutputToContain('Verein: Musikverein B')
            ->doesntExpectOutputToContain('Trachtenverein C')
            ->assertSuccessful();
    });

    it('migrates all club databases', function () {
        $this->artisan('tenants:migrate', ['--skip-landlord' => true])
            ->assertSuccessful();
    });
});
