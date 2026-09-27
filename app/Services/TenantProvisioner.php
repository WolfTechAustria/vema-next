<?php

namespace App\Services;

use App\Enums\TenantStatus;
use App\Models\Setting;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use RuntimeException;
use Spatie\Permission\Models\Role;

/**
 * Richtet einen registrierten Verein ein: eigene Datenbank (+ Test-DB für den
 * Testmodus), vollständige Migrationen, Vereinsdaten, erster Admin — und
 * startet die Testphase.
 */
class TenantProvisioner
{
    public function __construct(private TenantManager $tenantManager) {}

    /**
     * Datenbankname für einen neuen Verein; bei SQLite (Tests) ein Dateipfad.
     */
    public function databaseNameFor(string $slug): string
    {
        $name = config('tenancy.database_prefix').str_replace('-', '_', $slug);

        if ($this->tenantDriver() === 'sqlite') {
            return rtrim(config('tenancy.sqlite_directory'), '/\\').DIRECTORY_SEPARATOR.$name.'.sqlite';
        }

        return $name;
    }

    /**
     * @return string Link „Passwort festlegen“ für den ersten Admin
     */
    public function provision(Tenant $tenant): string
    {
        if ($tenant->provisioned_at !== null) {
            throw new RuntimeException("Verein {$tenant->slug} ist bereits eingerichtet.");
        }

        // Die komplette Migrationskette dauert je nach Server einige Sekunden —
        // im Web-Request (Bestätigungslink) nicht am PHP-Zeitlimit scheitern.
        set_time_limit(180);

        $this->createDatabase($tenant->database);
        $this->createDatabase($tenant->demoDatabase());

        $passwordSetupUrl = $this->tenantManager->runFor($tenant, function (Tenant $tenant): string {
            $exitCode = Artisan::call('migrate', [
                '--database' => $this->tenantManager->tenantConnection(),
                '--force' => true,
            ]);

            if ($exitCode !== 0) {
                throw new RuntimeException("Migrationen für {$tenant->slug} fehlgeschlagen: ".Artisan::output());
            }

            Setting::current()->update([
                'name' => $tenant->name,
                'email' => $tenant->contact_email,
            ]);

            $admin = $this->createAdmin($tenant);

            return route('password.reset', [
                'token' => Password::broker()->createToken($admin),
                'email' => $admin->email,
            ]);
        });

        File::ensureDirectoryExists($tenant->storagePath('private'));
        File::ensureDirectoryExists($tenant->storagePath('branding'));

        $tenant->forceFill([
            'status' => TenantStatus::Active,
            'provisioned_at' => now(),
            'trial_ends_at' => now()->addDays((int) config('tenancy.trial_days')),
        ])->save();

        return $passwordSetupUrl;
    }

    private function createAdmin(Tenant $tenant): User
    {
        $email = Str::lower($tenant->contact_email);

        $admin = User::create([
            'username' => $email,
            'email' => $email,
            // Unbenutzbar, bis über den Link ein eigenes Passwort gesetzt ist.
            'password' => Hash::make(Str::random(40)),
            'enabled' => true,
            'function' => 0,
            'companyname' => '',
            'companyname_short' => '',
            'registerDate' => now(),
            'memberID' => 0,
        ]);

        $admin->assignRole(Role::findOrCreate('admin', 'web'));

        return $admin;
    }

    private function createDatabase(string $database): void
    {
        if ($this->tenantDriver() === 'sqlite') {
            File::ensureDirectoryExists(dirname($database));

            if (! File::exists($database)) {
                File::put($database, '');
            }

            return;
        }

        // Name stammt aus Präfix + validiertem Slug; trotzdem nie ungeprüft ins SQL.
        if (! preg_match('/^[a-z0-9_]{1,64}$/', $database) || ! str_starts_with($database, config('tenancy.database_prefix'))) {
            throw new RuntimeException("Ungültiger Datenbankname: {$database}");
        }

        DB::connection($this->tenantManager->landlordConnection())->statement(
            "CREATE DATABASE IF NOT EXISTS `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
        );
    }

    private function tenantDriver(): string
    {
        return (string) config('database.connections.'.$this->tenantManager->tenantConnection().'.driver');
    }
}
