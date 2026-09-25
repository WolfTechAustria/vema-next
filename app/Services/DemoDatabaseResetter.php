<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use RuntimeException;

/**
 * Setzt die Test-Datenbank auf den aktuellen Live-Stand zurück: jede Tabelle
 * wird per CREATE TABLE … LIKE / INSERT … SELECT serverseitig kopiert
 * (kein mysqldump nötig, der DB-Benutzer braucht Rechte auf beide DBs).
 * Danach werden die gespeicherten Dateien (Belege, Anhänge, PDFs) kopiert.
 */
class DemoDatabaseResetter
{
    public function __construct(private DemoMode $demoMode) {}

    public function reset(): void
    {
        $this->ensureSafeToReset();

        $liveDatabase = $this->databaseName($this->demoMode->liveConnection());
        $demoDatabase = $this->databaseName($this->demoMode->demoConnection());
        $excludedTables = config('demo.excluded_tables', []);

        $demo = DB::connection($this->demoMode->demoConnection());

        // Legacy-Tabellen enthalten z. B. '0000-00-00' — im Strict-Mode würde
        // die 1:1-Kopie daran scheitern, daher nur für den Kopiervorgang aus.
        $originalSqlMode = $demo->selectOne('SELECT @@SESSION.sql_mode AS mode')->mode;

        $demo->statement("SET SESSION sql_mode = ''");
        $demo->statement('SET FOREIGN_KEY_CHECKS=0');

        try {
            foreach ($this->baseTables($demoDatabase) as $table) {
                $demo->statement("DROP TABLE IF EXISTS `{$demoDatabase}`.`{$table}`");
            }

            foreach ($this->baseTables($liveDatabase) as $table) {
                $demo->statement("CREATE TABLE `{$demoDatabase}`.`{$table}` LIKE `{$liveDatabase}`.`{$table}`");

                if (! in_array($table, $excludedTables, true)) {
                    $demo->statement("INSERT INTO `{$demoDatabase}`.`{$table}` SELECT * FROM `{$liveDatabase}`.`{$table}`");
                }
            }
        } finally {
            $demo->statement('SET FOREIGN_KEY_CHECKS=1');
            $demo->statement('SET SESSION sql_mode = ?', [$originalSqlMode]);
        }

        $this->copyFiles();

        $this->demoMode->settings()->update(['demo_last_reset_at' => now()]);
    }

    /**
     * Schutz davor, dass jemals die Live-Datenbank überschrieben wird.
     */
    private function ensureSafeToReset(): void
    {
        if ($this->demoMode->isActive()) {
            throw new RuntimeException('Die Testdaten können nicht aus dem Testmodus heraus zurückgesetzt werden.');
        }

        $live = config('database.connections.'.$this->demoMode->liveConnection());
        $demo = config('database.connections.'.$this->demoMode->demoConnection());

        if (! $live || ! $demo) {
            throw new RuntimeException('Die Datenbankverbindung für den Testmodus ist nicht konfiguriert.');
        }

        if (! in_array($live['driver'], ['mysql', 'mariadb'], true) || ! in_array($demo['driver'], ['mysql', 'mariadb'], true)) {
            throw new RuntimeException('Das Zurücksetzen der Testdaten wird nur für MySQL/MariaDB unterstützt.');
        }

        if (blank($demo['database']) || $demo['database'] === $live['database']) {
            throw new RuntimeException('Die Test-Datenbank muss eine eigene Datenbank sein (DB_DEMO_DATABASE).');
        }

        if (($demo['host'] ?? null) !== ($live['host'] ?? null) || (string) ($demo['port'] ?? '') !== (string) ($live['port'] ?? '')) {
            throw new RuntimeException('Test- und Live-Datenbank müssen auf demselben Server liegen.');
        }
    }

    private function databaseName(string $connection): string
    {
        return str_replace('`', '', (string) config("database.connections.{$connection}.database"));
    }

    /**
     * @return array<int, string>
     */
    private function baseTables(string $database): array
    {
        return collect(DB::connection($this->demoMode->demoConnection())->select(
            'SELECT TABLE_NAME AS name FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_TYPE = ?',
            [$database, 'BASE TABLE']
        ))->map(fn (object $row): string => str_replace('`', '', $row->name))->all();
    }

    private function copyFiles(): void
    {
        $liveRoot = config('filesystems.disks.local.root');
        $demoRoot = config('demo.storage_root');

        File::deleteDirectory($demoRoot);
        File::ensureDirectoryExists($demoRoot);

        if (File::isDirectory($liveRoot)) {
            File::copyDirectory($liveRoot, $demoRoot);
        }
    }
}
