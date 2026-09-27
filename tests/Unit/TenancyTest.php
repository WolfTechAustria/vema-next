<?php

use App\Enums\TenantStatus;
use App\Http\Middleware\IdentifyTenant;
use App\Models\Setting;
use App\Models\Tenant;
use App\Models\User;
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
use Illuminate\Support\Str;
use Tests\TestCase;

/*
 * Ohne RefreshDatabase: Die Tests bauen die App im Modus "multi" neu auf und
 * arbeiten mit eigenen SQLite-Dateien (zentral + je Verein).
 */
uses(TestCase::class);

const TENANCY_TEST_ENV = [
    'TENANCY_MODE',
    'TENANCY_CENTRAL_DOMAIN',
    'TENANCY_SCHEME',
    'DB_LANDLORD_DRIVER',
    'DB_LANDLORD_DATABASE',
    'DB_TENANT_DRIVER',
];

function setTenancyTestEnv(string $name, ?string $value): void
{
    if ($value === null) {
        putenv($name);
        unset($_ENV[$name], $_SERVER[$name]);

        return;
    }

    putenv("{$name}={$value}");
    $_ENV[$name] = $_SERVER[$name] = $value;
}

/**
 * Vollständig migrierte Vereinsdatenbank als Vorlage — einmal pro Testlauf,
 * danach wird sie für jeden Verein nur kopiert.
 */
function tenantTemplateDatabase(): string
{
    static $templatePath = null;

    if ($templatePath !== null && is_file($templatePath)) {
        return $templatePath;
    }

    $templatePath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'vema-tenant-template-'.getmypid().'.sqlite';
    File::put($templatePath, '');

    config(['database.connections.tenant_template' => [
        'driver' => 'sqlite',
        'database' => $templatePath,
        'prefix' => '',
        'foreign_key_constraints' => true,
    ]]);

    $previousDefault = DB::getDefaultConnection();
    DB::setDefaultConnection('tenant_template');

    try {
        Artisan::call('migrate', ['--database' => 'tenant_template', '--force' => true]);
    } finally {
        DB::setDefaultConnection($previousDefault);
        DB::purge('tenant_template');
    }

    return $templatePath;
}

/**
 * Legt einen Verein samt eigener (SQLite-)Datenbank an.
 *
 * @param  array<string, mixed>  $attributes
 */
function createTenantWithDatabase(string $slug, string $clubName, array $attributes = []): Tenant
{
    $databasePath = test()->tenancyDirectory.DIRECTORY_SEPARATOR.$slug.'.sqlite';
    File::copy(tenantTemplateDatabase(), $databasePath);

    $tenant = Tenant::factory()->create(array_merge([
        'slug' => $slug,
        'name' => $clubName,
        'database' => $databasePath,
    ], $attributes));

    app(TenantManager::class)->runFor($tenant, function () use ($clubName) {
        Setting::current()->update(['name' => $clubName]);
        User::factory()->create(['username' => 'vorstand']);
    });

    return $tenant;
}

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
        $this->tenancyDirectory = storage_path('framework/testing/tenancy-'.Str::random(8));
        File::ensureDirectoryExists($this->tenancyDirectory);
        File::put($this->tenancyDirectory.'/landlord.sqlite', '');

        foreach ([
            'TENANCY_MODE' => 'multi',
            'TENANCY_CENTRAL_DOMAIN' => 'vemat.test',
            'TENANCY_SCHEME' => 'http',
            'DB_LANDLORD_DRIVER' => 'sqlite',
            'DB_LANDLORD_DATABASE' => $this->tenancyDirectory.'/landlord.sqlite',
            'DB_TENANT_DRIVER' => 'sqlite',
        ] as $name => $value) {
            setTenancyTestEnv($name, $value);
        }

        // Modus und Routen werden beim Booten festgelegt.
        $this->refreshApplication();

        config(['tenancy.storage_root' => $this->tenancyDirectory.'/files']);

        $this->artisan('migrate', [
            '--database' => 'landlord',
            '--path' => 'database/migrations/landlord',
            '--force' => true,
        ])->assertSuccessful();

        $this->clubA = createTenantWithDatabase('verein-a', 'Schützenverein A');
        $this->clubB = createTenantWithDatabase('verein-b', 'Musikverein B');
    });

    afterEach(function () {
        DB::purge('landlord');
        DB::purge('tenant');

        foreach (TENANCY_TEST_ENV as $name) {
            setTenancyTestEnv($name, null);
        }

        File::deleteDirectory($this->tenancyDirectory);
    });

    it('works against the central database until a club is identified', function () {
        expect(DB::getDefaultConnection())->toBe('landlord')
            ->and(config('session.connection'))->toBe('landlord');
    });

    it('serves the platform page only on the central domain', function () {
        $this->get('http://vemat.test/')->assertOk()->assertSee('VEMA');

        $this->get('http://vemat.test/login')->assertNotFound();
        $this->get('http://verein-a.vemat.test/')->assertRedirect();
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
        'pending' => [['status' => TenantStatus::Pending], 'erst nach der Freischaltung'],
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
