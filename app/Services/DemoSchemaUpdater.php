<?php

namespace App\Services;

use Illuminate\Database\Migrations\DatabaseMigrationRepository;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

/**
 * Die Test-Datenbank wird nur beim Zurücksetzen aus der Live-DB kopiert —
 * "migrate"/"tenants:migrate" migrieren sie nicht mit. Nach einem Update
 * fehlen ihr sonst neue Tabellen und Spalten, bis jemand die Testdaten
 * zurücksetzt. Spielt deshalb fehlende Migrationen nach, ohne die
 * Testdaten zu verwerfen.
 */
class DemoSchemaUpdater
{
    public function __construct(private DemoMode $demoMode) {}

    /**
     * @return list<string>
     */
    public function pendingMigrations(): array
    {
        $repository = new DatabaseMigrationRepository(app('db'), config('database.migrations.table', 'migrations'));
        $repository->setSource($this->demoMode->demoConnection());

        // Nie zurückgesetzte Test-DB: es gibt nichts nachzuziehen.
        if (! $repository->repositoryExists()) {
            return [];
        }

        $ran = $repository->getRan();

        // Gleiche Namensbildung wie "migrate" selbst (auch für Dateien wie *.php.php).
        return collect(app('migrator')->getMigrationFiles([database_path('migrations')]))
            ->keys()
            ->reject(fn (string $migration): bool => in_array($migration, $ran, true))
            ->sort()
            ->values()
            ->all();
    }

    public function migratePending(): void
    {
        if ($this->pendingMigrations() === []) {
            return;
        }

        $connection = $this->demoMode->demoConnection();
        $database = (string) config("database.connections.{$connection}.database");

        Cache::lock('demo-schema-update:'.$database, 120)->block(60, function () use ($connection) {
            // Ein paralleler Request kann bereits migriert haben.
            if ($this->pendingMigrations() === []) {
                return;
            }

            $exitCode = Artisan::call('migrate', [
                '--database' => $connection,
                '--force' => true,
            ]);

            if ($exitCode !== 0) {
                throw new RuntimeException('Die Test-Datenbank konnte nicht aktualisiert werden: '.trim(Artisan::output()));
            }
        });
    }
}
