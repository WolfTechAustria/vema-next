<?php

namespace App\Console\Commands;

use App\Services\PlatformNewsDrafts;
use App\Services\TenantManager;
use Illuminate\Console\Command;

class SyncPlatformNewsDrafts extends Command
{
    protected $signature = 'platform-news:sync';

    protected $description = 'Übernimmt mitgelieferte Neuigkeiten (resources/news) als Entwürfe in den Plattform-Admin';

    public function handle(TenantManager $tenantManager, PlatformNewsDrafts $drafts): int
    {
        if (! $tenantManager->isMultiTenant()) {
            $this->info('Neuigkeiten gibt es nur im Plattform-Betrieb.');

            return self::SUCCESS;
        }

        $count = $drafts->sync();

        $this->info($count.' neue(r) Entwurf/Entwürfe übernommen.');

        return self::SUCCESS;
    }
}
