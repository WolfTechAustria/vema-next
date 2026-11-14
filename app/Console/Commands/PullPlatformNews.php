<?php

namespace App\Console\Commands;

use App\Services\PlatformNewsPull;
use App\Services\TenantManager;
use Illuminate\Console\Command;
use Throwable;

class PullPlatformNews extends Command
{
    protected $signature = 'platform-news:pull';

    protected $description = 'Holt die Neuigkeiten zu VEMA von der Plattform (nur eigene Installationen)';

    public function handle(TenantManager $tenantManager, PlatformNewsPull $pull): int
    {
        if ($tenantManager->isMultiTenant()) {
            $this->info('Auf der Plattform werden Neuigkeiten direkt verwaltet.');

            return self::SUCCESS;
        }

        if (! $pull->isConfigured()) {
            $this->info('Neuigkeiten-Feed ist nicht eingerichtet (TENANCY_NEWS_FEED_TOKEN).');

            return self::SUCCESS;
        }

        try {
            $result = $pull->pull();
        } catch (Throwable $exception) {
            report($exception);
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf(
            '%d neu, %d geändert, %d entfernt, %d gemailt.',
            $result['created'], $result['updated'], $result['deleted'], $result['emailed'],
        ));

        return self::SUCCESS;
    }
}
