<?php

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
