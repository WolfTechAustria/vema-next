<?php

namespace App\Console\Commands;

use App\Enums\Feature;
use App\Models\ClubEventSource;
use App\Services\ActivityLogger;
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
            $previousError = $source->last_sync_error;

            $count = $syncService->sync($source);

            if ($source->last_sync_error) {
                $this->warn($source->name.': '.$source->last_sync_error);

                // Nur bei neuem/geändertem Fehler protokollieren, nicht stündlich denselben.
                if ($source->last_sync_error !== $previousError) {
                    ActivityLogger::log(
                        'event.source_sync_failed',
                        'Automatische Synchronisierung des externen Kalenders „'.$source->name.'“ fehlgeschlagen: '.$source->last_sync_error,
                        'ClubEventSource',
                        $source->sourceID
                    );
                }
            } else {
                $this->info($source->name.': '.$count.' Termine synchronisiert.');
            }
        }

        return self::SUCCESS;
    }
}
