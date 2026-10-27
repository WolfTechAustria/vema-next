<?php

namespace App\Console\Commands;

use App\Models\FederationMembership;
use App\Models\Tenant;
use App\Services\ClubStatistics;
use App\Services\TenantManager;
use Illuminate\Console\Command;
use Throwable;

class FederationCollectStatistics extends Command
{
    protected $signature = 'federation:collect-statistics';

    protected $description = 'Aktualisiert die Kennzahlen aller Vereine, die einem Verband angehören (TENANCY_MODE=multi)';

    public function handle(TenantManager $tenantManager, ClubStatistics $statistics): int
    {
        if (! $tenantManager->isMultiTenant()) {
            $this->error('Nur im Modus TENANCY_MODE=multi verfügbar.');

            return self::FAILURE;
        }

        $clubs = Tenant::query()
            ->whereIn('id', FederationMembership::query()->active()->select('club_id'))
            ->whereNotNull('provisioned_at')
            ->get()
            ->filter(fn (Tenant $club) => $club->isAccessible());

        $failed = 0;

        foreach ($clubs as $club) {
            try {
                $tenantManager->runFor($club, fn (Tenant $club) => $statistics->store($club));
            } catch (Throwable $exception) {
                report($exception);
                $this->error("{$club->slug}: {$exception->getMessage()}");
                $failed++;
            }
        }

        $this->info(($clubs->count() - $failed).' Vereine aktualisiert.');

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
