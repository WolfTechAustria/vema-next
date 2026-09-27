<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\TenantManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class TenantsMigrate extends Command
{
    protected $signature = 'tenants:migrate
        {--tenant=* : Nur diese Vereine (Slug)}
        {--skip-landlord : Zentrale Datenbank nicht migrieren}';

    protected $description = 'Migriert die zentrale Datenbank und die Datenbanken aller Vereine (TENANCY_MODE=multi)';

    public function handle(TenantManager $tenantManager): int
    {
        if (! $tenantManager->isMultiTenant()) {
            $this->error('Nur im Modus TENANCY_MODE=multi verfügbar — sonst "php artisan migrate" verwenden.');

            return self::FAILURE;
        }

        if (! $this->option('skip-landlord')) {
            $this->components->info('Zentrale Datenbank');

            $exitCode = $this->call('migrate', [
                '--database' => $tenantManager->landlordConnection(),
                '--path' => 'database/migrations/landlord',
                '--force' => true,
            ]);

            if ($exitCode !== self::SUCCESS) {
                return $exitCode;
            }
        }

        $tenants = Tenant::query()
            ->when($this->option('tenant'), fn ($query, array $slugs) => $query->whereIn('slug', $slugs))
            ->orderBy('id')
            ->get();

        $failed = 0;

        foreach ($tenants as $tenant) {
            $this->components->info("Verein {$tenant->slug} ({$tenant->database})");

            $exitCode = $tenantManager->runFor($tenant, fn () => Artisan::call('migrate', [
                '--database' => $tenantManager->tenantConnection(),
                '--force' => true,
            ], $this->output));

            if ($exitCode !== self::SUCCESS) {
                $failed++;
                $this->components->error("Migration für {$tenant->slug} fehlgeschlagen.");
            }
        }

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
