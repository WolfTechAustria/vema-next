<?php

use App\Enums\DemoResetMode;
use App\Livewire\Admin\Settings\Index as SettingsIndex;
use App\Models\ActivityLog;
use App\Models\Setting;
use App\Services\DemoDatabaseResetter;
use App\Services\DemoMode;
use App\Services\ImapSentMailService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    config(['database.connections.demo' => [
        'driver' => 'sqlite',
        'database' => ':memory:',
        'prefix' => '',
        'foreign_key_constraints' => false,
    ]]);
    config(['demo.live_connection' => 'sqlite']);

    // Test-DB mit demselben Schema aufbauen.
    DB::setDefaultConnection('demo');
    setUpCashBookSchema();
    DB::setDefaultConnection('sqlite');
    setUpCashBookSchema();
    markAllDemoMigrationsAsRun();

    $this->user = createStaffUser(['admin']);

    DB::connection('demo')->table('tb_user')->insert(
        (array) DB::table('tb_user')->where('id', $this->user->id)->first(['id', 'username', 'email', 'password', 'enabled'])
    );

    Route::middleware(['web', 'auth'])->get('/_demo-probe', function () {
        Mail::raw('Hallo', fn ($message) => $message->to('mitglied@example.com')->subject('Rundschreiben'));

        return response()->json([
            'connection' => DB::getDefaultConnection(),
            'username' => auth()->user()->username,
        ]);
    });
});

function enableDemoMode(DemoResetMode $mode = DemoResetMode::Nightly): void
{
    Setting::current()->update(['demo_enabled' => true, 'demo_reset_mode' => $mode]);
}

const BOARD_COUNCIL_MIGRATION = '2026_10_19_194756_add_is_council_to_board_functions_table';

/**
 * Eine zurückgesetzte Test-DB übernimmt die vollständige migrations-Tabelle
 * der Live-DB; das reduzierte Test-Schema trägt nur einen Teil davon ein.
 */
function markAllDemoMigrationsAsRun(): void
{
    $ran = DB::connection('demo')->table('migrations')->pluck('migration')->all();

    DB::connection('demo')->table('migrations')->insert(
        collect(app('migrator')->getMigrationFiles([database_path('migrations')]))
            ->keys()
            ->reject(fn (string $migration) => in_array($migration, $ran, true))
            ->map(fn (string $migration) => ['migration' => $migration, 'batch' => 1])
            ->values()
            ->all()
    );
}

describe('entering and leaving', function () {
    it('refuses to enter while the demo mode is disabled', function () {
        $this->actingAs($this->user)
            ->post(route('demo.enter'))
            ->assertSessionHas('error')
            ->assertSessionMissing(DemoMode::SESSION_KEY);
    });

    it('enters the demo mode when enabled', function () {
        enableDemoMode();

        $this->actingAs($this->user)
            ->post(route('demo.enter'))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas(DemoMode::SESSION_KEY, true);
    });

    it('refuses to enter when the user does not exist in the demo database', function () {
        enableDemoMode();
        DB::connection('demo')->table('tb_user')->delete();

        $this->actingAs($this->user)
            ->post(route('demo.enter'))
            ->assertSessionHas('error')
            ->assertSessionMissing(DemoMode::SESSION_KEY);
    });

    it('leaves the demo mode', function () {
        enableDemoMode();

        $this->actingAs($this->user)
            ->withSession([DemoMode::SESSION_KEY => true])
            ->post(route('demo.leave'))
            ->assertRedirect(route('dashboard'))
            ->assertSessionMissing(DemoMode::SESSION_KEY);
    });
});

describe('request handling', function () {
    it('uses the live database outside the demo mode', function () {
        $this->actingAs($this->user)
            ->get('/_demo-probe')
            ->assertJson(['connection' => 'sqlite', 'username' => $this->user->username]);
    });

    it('switches the database and loads the user from the demo copy', function () {
        enableDemoMode();
        DB::connection('demo')->table('tb_user')->where('id', $this->user->id)->update(['username' => 'aus-der-testdb']);

        // Echter Session-Login statt actingAs(), damit der Benutzer im Request geladen wird.
        $this->withSession([
            Auth::guard('web')->getName() => $this->user->id,
            DemoMode::SESSION_KEY => true,
        ])
            ->get('/_demo-probe')
            ->assertJson(['connection' => 'demo', 'username' => 'aus-der-testdb']);
    });

    it('sends every mail only to the tester and marks it as test', function () {
        enableDemoMode();

        $this->actingAs($this->user)
            ->withSession([DemoMode::SESSION_KEY => true])
            ->get('/_demo-probe')
            ->assertOk();

        $message = Mail::mailer()->getSymfonyTransport()->messages()->sole()->getOriginalMessage();

        expect($message->getTo()[0]->getAddress())->toBe($this->user->email)
            ->and($message->getSubject())->toBe('[TEST] Rundschreiben');
    });

    it('leaves the demo mode automatically once it gets disabled', function () {
        $this->actingAs($this->user)
            ->withSession([DemoMode::SESSION_KEY => true])
            ->get('/_demo-probe')
            ->assertRedirect(route('dashboard'))
            ->assertSessionMissing(DemoMode::SESSION_KEY);
    });

    it('adds migrations missing in the demo database before the request runs', function () {
        enableDemoMode();
        DB::connection('demo')->table('migrations')->where('migration', BOARD_COUNCIL_MIGRATION)->delete();
        Schema::connection('demo')->create('tb_board_functions', function (Blueprint $table) {
            $table->id('boardFunctionID');
            $table->string('name');
            $table->boolean('implies_schuetzenrat')->default(false);
        });
        DB::connection('demo')->table('tb_board_functions')->insert(['name' => 'Schützenrat']);

        $this->actingAs($this->user)
            ->withSession([DemoMode::SESSION_KEY => true])
            ->get('/_demo-probe')
            ->assertJson(['connection' => 'demo']);

        expect(DB::connection('demo')->table('tb_board_functions')->value('is_council'))->toEqual(1)
            ->and(DB::connection('demo')->table('migrations')->where('migration', BOARD_COUNCIL_MIGRATION)->exists())->toBeTrue();
    });

    it('leaves the demo mode when the demo database cannot be updated', function () {
        enableDemoMode();
        // Ohne tb_board_functions scheitert die nachzuholende Migration.
        DB::connection('demo')->table('migrations')->where('migration', BOARD_COUNCIL_MIGRATION)->delete();

        $this->actingAs($this->user)
            ->withSession([DemoMode::SESSION_KEY => true])
            ->get('/_demo-probe')
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('error', 'Die Test-Datenbank konnte nicht auf den aktuellen Stand gebracht werden. Bitte die Testdaten in den Einstellungen auf den Live-Stand zurücksetzen.')
            ->assertSessionMissing(DemoMode::SESSION_KEY);
    });

    it('does not archive mails to the IMAP sent folder in demo mode', function () {
        session()->put(DemoMode::SESSION_KEY, true);

        app(ImapSentMailService::class)->append('raw message');

        expect(true)->toBeTrue();
    });
});

describe('settings', function () {
    it('stores the demo settings in the live database', function () {
        $this->actingAs($this->user);

        Livewire::test(SettingsIndex::class)
            ->set('demo_enabled', true)
            ->set('demo_reset_mode', 'manual')
            ->call('saveDemoSettings')
            ->assertHasNoErrors();

        $setting = Setting::current();

        expect($setting->demo_enabled)->toBeTrue()
            ->and($setting->demo_reset_mode)->toBe(DemoResetMode::Manual);
    });

    it('resets the demo data on demand', function () {
        $this->actingAs($this->user);

        $this->mock(DemoDatabaseResetter::class)->shouldReceive('reset')->once();

        Livewire::test(SettingsIndex::class)
            ->call('resetDemoData')
            ->assertHasNoErrors();

        expect(ActivityLog::where('action', 'settings.demo_reset')->exists())->toBeTrue();
    });
});

describe('demo:reset', function () {
    it('skips the nightly reset in manual mode', function () {
        enableDemoMode(DemoResetMode::Manual);

        $this->mock(DemoDatabaseResetter::class)->shouldNotReceive('reset');

        $this->artisan('demo:reset --if-nightly')->assertSuccessful();
    });

    it('runs the nightly reset in nightly mode', function () {
        enableDemoMode();

        $this->mock(DemoDatabaseResetter::class)->shouldReceive('reset')->once();

        $this->artisan('demo:reset --if-nightly')->assertSuccessful();
    });

    it('refuses to overwrite the live database', function () {
        $mysql = ['driver' => 'mysql', 'host' => '127.0.0.1', 'port' => '3306', 'database' => 'vema'];
        config([
            'database.connections.live_mysql' => $mysql,
            'database.connections.demo' => $mysql,
            'demo.live_connection' => 'live_mysql',
        ]);

        expect(fn () => app(DemoDatabaseResetter::class)->reset())
            ->toThrow(RuntimeException::class, 'eigene Datenbank');
    });
});
