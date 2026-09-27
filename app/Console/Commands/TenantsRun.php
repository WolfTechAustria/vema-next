<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\TenantManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Throwable;

class TenantsRun extends Command
{
    protected $signature = 'tenants:run
        {artisanCommand : Auszuführender Befehl samt Optionen, z. B. "demo:reset --if-nightly"}
        {--tenant= : Nur für diesen Verein (Slug)}';

    protected $description = 'Führt einen Artisan-Befehl für jeden freigeschalteten Verein aus (im Modus "single" einmal)';

    public function handle(TenantManager $tenantManager): int
    {
        $artisanCommand = (string) $this->argument('artisanCommand');

        if (! $tenantManager->isMultiTenant()) {
            return Artisan::call($artisanCommand, [], $this->output);
        }

        if ($slug = $this->option('tenant')) {
            $tenant = Tenant::query()->where('slug', $slug)->first();

            if (! $tenant) {
                $this->error("Verein {$slug} nicht gefunden.");

                return self::FAILURE;
            }

            return $tenantManager->runFor($tenant, fn () => Artisan::call($artisanCommand, [], $this->output));
        }

        $failed = 0;

        $tenantManager->eachAccessible(function (Tenant $tenant) use ($artisanCommand, &$failed): void {
            $this->components->info("Verein {$tenant->slug}");

            // Ein fehlerhafter Verein darf die übrigen nicht blockieren.
            try {
                if (Artisan::call($artisanCommand, [], $this->output) !== self::SUCCESS) {
                    $failed++;
                }
            } catch (Throwable $exception) {
                report($exception);
                $failed++;
                $this->components->error("{$tenant->slug}: {$exception->getMessage()}");
            }
        });

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
