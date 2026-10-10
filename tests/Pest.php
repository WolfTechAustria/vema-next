<?php

use App\Models\Member;
use App\Models\MemberAccount;
use App\Models\MemberEmail;
use App\Models\Setting;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantManager;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/**
 * Baut nur das für das Kassabuch nötige Schema auf (schneller als die
 * vollständige Migrationskette, die Feature-Tests per RefreshDatabase nutzen).
 */
function setUpCashBookSchema(): void
{
    Schema::create('tb_user', function (Blueprint $table) {
        $table->increments('id');
        $table->string('username')->nullable();
        $table->string('email')->nullable();
        $table->string('password')->nullable();
        $table->boolean('enabled')->default(true);
        $table->unsignedBigInteger('memberID')->nullable();
    });

    Schema::create('tb_member_accounts', function (Blueprint $table) {
        $table->bigIncrements('accountID');
    });

    test()->artisan('migrate', [
        '--path' => [
            'database/migrations/2026_09_20_122656_create_permission_tables.php',
            'database/migrations/2026_09_20_130000_create_settings_table.php',
            'database/migrations/2026_09_20_140000_create_activity_log_table.php',
            'database/migrations/2026_09_21_090500_add_invoice_fields_to_settings_table.php',
            'database/migrations/2026_09_25_031630_create_cash_book_years_table.php',
            'database/migrations/2026_09_25_031632_create_cash_book_entries_table.php',
            'database/migrations/2026_09_25_031634_create_cash_book_attachments_table.php',
            'database/migrations/2026_09_25_184245_add_demo_mode_to_settings_table.php',
            'database/migrations/2026_09_26_191133_add_branding_and_mail_to_settings_table.php',
        ],
    ])->assertSuccessful();
}

/**
 * @param  array<int, string>  $roles
 */
function createStaffUser(array $roles = ['kassier']): User
{
    $user = User::create([
        'username' => 'kassier-'.Str::random(6),
        'email' => fake()->unique()->safeEmail(),
        'password' => bcrypt('password'),
        'enabled' => true,
    ]);

    foreach ($roles as $role) {
        Role::findOrCreate($role, 'web');
        $user->assignRole($role);
    }

    return $user;
}

/**
 * Aktives Mitglied mit Portal-Zugang; die Session zeigt auf dieses Profil.
 */
function loginPortalMember(string $surname = 'Muster'): Member
{
    $email = strtolower($surname).'@example.test';

    $member = Member::create([
        'gender' => 'm',
        'name' => 'Max',
        'surname' => $surname,
        'active' => 1,
    ]);

    MemberEmail::create(['memberID' => $member->memberID, 'email' => $email]);

    $account = MemberAccount::create(['memberID' => $member->memberID, 'email' => $email]);

    test()->actingAs($account, 'member');
    session(['active_member_id' => $member->memberID]);

    return $member;
}

/*
|--------------------------------------------------------------------------
| Mandantenfähigkeit (TENANCY_MODE=multi)
|--------------------------------------------------------------------------
|
| Modus und Plattform-Routen werden beim Booten festgelegt — die Tests setzen
| deshalb ENV-Variablen und bauen die App neu auf. Zentrale DB und
| Vereinsdatenbanken sind SQLite-Dateien in einem Temp-Ordner.
|
*/

const TENANCY_TEST_ENV = [
    'TENANCY_MODE',
    'TENANCY_PLATFORM_HOST',
    'TENANCY_TENANT_DOMAIN',
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
 * Plattform app.vemat.test, Vereine unter <slug>.vemat.test.
 */
function bootMultiTenancyForTests(): void
{
    $directory = storage_path('framework/testing/tenancy-'.Str::random(8));
    File::ensureDirectoryExists($directory);
    File::put($directory.'/landlord.sqlite', '');

    test()->tenancyDirectory = $directory;

    // Vorherige Werte (phpunit.xml) merken, damit spätere Tests unberührt bleiben.
    $originalEnv = [];

    foreach (TENANCY_TEST_ENV as $name) {
        $original = getenv($name);
        $originalEnv[$name] = $original === false ? null : $original;
    }

    test()->tenancyOriginalEnv = $originalEnv;

    foreach ([
        'TENANCY_MODE' => 'multi',
        'TENANCY_PLATFORM_HOST' => 'app.vemat.test',
        'TENANCY_TENANT_DOMAIN' => 'vemat.test',
        'TENANCY_SCHEME' => 'http',
        'DB_LANDLORD_DRIVER' => 'sqlite',
        'DB_LANDLORD_DATABASE' => $directory.'/landlord.sqlite',
        'DB_TENANT_DRIVER' => 'sqlite',
    ] as $name => $value) {
        setTenancyTestEnv($name, $value);
    }

    test()->refreshApplication();

    config([
        'tenancy.storage_root' => $directory.'/files',
        'tenancy.sqlite_directory' => $directory.'/databases',
    ]);

    test()->artisan('migrate', [
        '--database' => 'landlord',
        '--path' => 'database/migrations/landlord',
        '--force' => true,
    ])->assertSuccessful();
}

function shutdownMultiTenancyForTests(): void
{
    DB::purge('landlord');
    DB::purge('tenant');

    foreach (test()->tenancyOriginalEnv as $name => $value) {
        setTenancyTestEnv($name, $value);
    }

    File::deleteDirectory(test()->tenancyDirectory);
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
    register_shutdown_function(fn () => @unlink($templatePath));

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
