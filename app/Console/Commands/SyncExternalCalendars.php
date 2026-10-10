<?php

namespace App\Console\Commands;

use App\Enums\Feature;
use App\Models\ClubEventSource;
use App\Services\ExternalCalendarSyncService;
use App\Services\PlanEntitlements;
use Illuminate\Console\Command;

class SyncExternalCalendars extends Command
{
    protected $signature = 'events:sync-external';

    protected $description = 'Übernimmt Termine aus den hinterlegten externen iCal-Kalendern';

    public function handle(PlanEntitlements $entitlements, ExternalCalendarSyncService $syncService): int
    {
        // Vereinstermine sind nicht in jedem Paket enthalten (Plattform-Betrieb).
        if (! $entitlements->allows(Feature::Events)) {
            $this->info('Vereinstermine sind im Paket dieses Vereins nicht enthalten.');

            return self::SUCCESS;
        }

        $sources = ClubEventSource::query()
            ->where('active', true)
            ->get();

        foreach ($sources as $source) {
            $count = $syncService->sync($source);

            if ($source->last_sync_error) {
                $this->warn($source->name.': '.$source->last_sync_error);
            } else {
                $this->info($source->name.': '.$count.' Termine synchronisiert.');
            }
        }

        return self::SUCCESS;
    }
}
