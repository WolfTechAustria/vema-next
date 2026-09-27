<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\TenantManager;
use Illuminate\Console\Command;

class TenantsList extends Command
{
    protected $signature = 'tenants:list';

    protected $description = 'Listet alle Vereine der Plattform auf (TENANCY_MODE=multi)';

    public function handle(TenantManager $tenantManager): int
    {
        if (! $tenantManager->isMultiTenant()) {
            $this->error('Nur im Modus TENANCY_MODE=multi verfügbar.');

            return self::FAILURE;
        }

        $this->table(
            ['ID', 'Slug', 'Name', 'Datenbank', 'Status', 'Lizenz bis', 'Zugang'],
            Tenant::query()->orderBy('id')->get()->map(fn (Tenant $tenant) => [
                $tenant->id,
                $tenant->slug,
                $tenant->name,
                $tenant->database,
                $tenant->status->label(),
                $tenant->license_valid_until?->format('d.m.Y') ?? 'unbegrenzt',
                $tenant->isAccessible() ? 'ja' : 'nein',
            ])
        );

        return self::SUCCESS;
    }
}
