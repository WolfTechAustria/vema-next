<?php

namespace App\Console\Commands;

use App\Enums\TenantStatus;
use App\Models\Tenant;
use App\Services\TenantManager;
use Illuminate\Console\Command;

class TenantsPruneUnverified extends Command
{
    protected $signature = 'tenants:prune-unverified';

    protected $description = 'Löscht Registrierungen, deren Bestätigungslink abgelaufen ist (gibt die Adresse wieder frei)';

    public function handle(TenantManager $tenantManager): int
    {
        if (! $tenantManager->isMultiTenant()) {
            $this->error('Nur im Modus TENANCY_MODE=multi verfügbar.');

            return self::FAILURE;
        }

        // Nur nie bestätigte Registrierungen — dafür existiert noch keine Datenbank.
        $deleted = Tenant::query()
            ->where('status', TenantStatus::Pending)
            ->whereNull('email_verified_at')
            ->whereNull('provisioned_at')
            ->where('created_at', '<', now()->subHours((int) config('tenancy.verification_hours')))
            ->delete();

        $this->info("{$deleted} unbestätigte Registrierung(en) gelöscht.");

        return self::SUCCESS;
    }
}
