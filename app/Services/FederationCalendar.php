<?php

namespace App\Services;

use App\Models\ClubEvent;
use App\Models\ClubEventSource;
use App\Models\FederationEvent;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;

/**
 * Termine, die ein Verband für seine Mitgliedsvereine freigibt.
 *
 * Der Verband veröffentlicht freigegebene Termine in die zentrale Datenbank;
 * jeder Mitgliedsverein hat dafür eine Kalenderquelle, die wie ein externer
 * iCal-Kalender synchronisiert wird (ExternalCalendarSyncService).
 */
class FederationCalendar
{
    /**
     * Freigegebene Termine des aktuellen Mandanten (Verband) veröffentlichen.
     */
    public function publish(Tenant $federation): void
    {
        $rows = ClubEvent::query()
            ->whereNull('sourceID')
            ->where('share_with_clubs', true)
            ->where('starts_at', '>=', now()->subMonth()->startOfDay())
            ->where('starts_at', '<=', now()->addYear())
            ->get()
            ->map(fn (ClubEvent $event) => [
                'federation_id' => $federation->id,
                'uid' => 'vema-event-'.$event->eventID,
                'title' => $event->title,
                'description' => $event->description,
                'location' => $event->location,
                'starts_at' => $event->starts_at,
                'ends_at' => $event->ends_at,
                'all_day' => $event->all_day,
            ]);

        DB::connection('landlord')->transaction(function () use ($federation, $rows) {
            FederationEvent::query()->where('federation_id', $federation->id)->delete();

            foreach ($rows->chunk(200) as $chunk) {
                FederationEvent::query()->insert($chunk->values()->all());
            }
        });
    }

    /**
     * Kalenderquelle des Verbands im aktuellen Verein anlegen und füllen.
     */
    public function connect(Tenant $federation, ExternalCalendarSyncService $syncService): ClubEventSource
    {
        $source = ClubEventSource::query()->firstOrCreate(
            ['federation_id' => $federation->id],
            ['name' => $federation->name, 'url' => null, 'active' => true, 'rsvp_enabled' => false, 'show_on_homepage' => false],
        );

        $syncService->sync($source);

        return $source;
    }

    /**
     * Kalenderquelle des Verbands samt übernommener Termine im aktuellen Verein entfernen.
     */
    public function disconnect(Tenant|int $federation): void
    {
        $federationId = $federation instanceof Tenant ? $federation->id : $federation;

        ClubEventSource::query()
            ->where('federation_id', $federationId)
            ->get()
            ->each(function (ClubEventSource $source) {
                $source->events()->delete();
                $source->delete();
            });
    }
}
