<?php

namespace App\Console\Commands;

use App\Enums\DemoResetMode;
use App\Services\DemoDatabaseResetter;
use App\Services\DemoMode;
use Illuminate\Console\Command;
use Throwable;

class DemoReset extends Command
{
    protected $signature = 'demo:reset {--if-nightly : Nur zurücksetzen, wenn in den Einstellungen "jede Nacht" gewählt ist}';

    protected $description = 'Setzt die Test-Datenbank (Testmodus) auf den aktuellen Live-Stand zurück';

    public function handle(DemoMode $demoMode, DemoDatabaseResetter $resetter): int
    {
        if ($this->option('if-nightly')) {
            $settings = $demoMode->settings();

            if (! $settings->demo_enabled || $settings->demo_reset_mode !== DemoResetMode::Nightly) {
                $this->info('Nächtliches Zurücksetzen ist nicht aktiviert.');

                return self::SUCCESS;
            }
        }

        try {
            $resetter->reset();
        } catch (Throwable $exception) {
            report($exception);
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Testdaten wurden auf den Live-Stand zurückgesetzt.');

        return self::SUCCESS;
    }
}
