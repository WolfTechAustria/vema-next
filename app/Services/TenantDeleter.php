<?php

namespace App\Services;

use App\Models\Tenant;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use RuntimeException;

/**
 * Löscht einen Verein endgültig: Datenbank, Test-Datenbank, Dateien, eigene
 * Domains und den Eintrag in der zentralen Datenbank.
 */
class TenantDeleter
{
    public function __construct(private TenantManager $tenantManager) {}

    public function delete(Tenant $tenant): void
    {
        if ($this->tenantManager->current()?->is($tenant)) {
            throw new RuntimeException('Der aktive Verein kann nicht gelöscht werden.');
        }

        // Nie bestätigte Registrierungen haben noch keine Datenbank.
        if ($tenant->provisioned_at !== null) {
            $this->dropDatabase($tenant->demoDatabase());
            $this->dropDatabase($tenant->database);
        }

        File::deleteDirectory($tenant->storagePath());

        foreach ($tenant->domains()->pluck('domain') as $domain) {
            Cache::forget('homepage.host.'.$domain);
        }

        // Nicht auf ON DELETE CASCADE verlassen (SQLite ohne Fremdschlüssel).
        $tenant->domains()->delete();
        $tenant->events()->delete();
        $tenant->delete();
    }

    private function dropDatabase(string $database): void
    {
        $connection = $this->tenantManager->tenantConnection();

        if (config("database.connections.{$connection}.driver") === 'sqlite') {
            File::delete($database);

            return;
        }

        // Nur Vereinsdatenbanken (Präfix) — niemals die zentrale oder fremde.
        if (! preg_match('/^[a-z0-9_]{1,64}$/', $database) || ! str_starts_with($database, config('tenancy.database_prefix'))) {
            throw new RuntimeException("Ungültiger Datenbankname: {$database}");
        }

        DB::connection($this->tenantManager->landlordConnection())->statement("DROP DATABASE IF EXISTS `{$database}`");
    }
}
