<?php

namespace App\Services;

use App\Models\ClubEvent;
use App\Models\ClubEventSource;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Sabre\VObject\Component\VCalendar;
use Sabre\VObject\Component\VEvent;
use Sabre\VObject\ParseException;
use Sabre\VObject\Reader;
use Throwable;

/**
 * Übernimmt Termine aus externen iCal-Links in tb_events.
 *
 * Serientermine werden im Zeitfenster (1 Monat zurück bis 1 Jahr voraus)
 * zu Einzelterminen expandiert. Zeiten werden in die Vereinszeitzone
 * umgerechnet und – wie eigene Termine – als Wanduhrzeit gespeichert.
 */
class ExternalCalendarSyncService
{
    /**
     * Synchronisiert eine Quelle und liefert die Anzahl der Termine im Feed.
     * Fehler werden an der Quelle vermerkt; bestehende Termine bleiben dann
     * unverändert.
     */
    public function sync(ClubEventSource $source): int
    {
        try {
            $calendar = $this->fetchCalendar($source->url);
            $count = $this->importEvents($source, $calendar);
        } catch (ExternalCalendarException $exception) {
            $source->update(['last_sync_error' => $exception->getMessage()]);

            return 0;
        } catch (Throwable $exception) {
            report($exception);

            $source->update(['last_sync_error' => 'Der Kalender konnte nicht gelesen werden.']);

            return 0;
        }

        $source->update([
            'last_synced_at' => now(),
            'last_sync_error' => null,
        ]);

        return $count;
    }

    /**
     * Prüft, ob ein Link abgerufen werden darf: nur http(s)/webcal und nur
     * öffentliche Adressen (kein Zugriff auf interne Dienste des Servers).
     */
    public function assertFetchableUrl(string $url): void
    {
        $normalized = $this->normalizeUrl($url);
        $host = parse_url($normalized, PHP_URL_HOST);

        if (! in_array(parse_url($normalized, PHP_URL_SCHEME), ['http', 'https'], true) || ! is_string($host) || $host === '') {
            throw new ExternalCalendarException('Bitte einen gültigen iCal-Link (https:// oder webcal://) angeben.');
        }

        $host = trim($host, '[]');
        $addresses = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : $this->resolveHost($host);

        if ($addresses === []) {
            throw new ExternalCalendarException('Der Server „'.$host.'“ wurde nicht gefunden.');
        }

        foreach ($addresses as $address) {
            if (! filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                throw new ExternalCalendarException('Links auf interne Adressen sind nicht erlaubt.');
            }
        }
    }

    public function normalizeUrl(string $url): string
    {
        return preg_replace('#^webcals?://#i', 'https://', trim($url));
    }

    private function fetchCalendar(string $url): VCalendar
    {
        $currentUrl = $this->normalizeUrl($url);

        // Weiterleitungen selbst folgen, damit auch jedes Ziel geprüft wird.
        for ($redirects = 0; ; $redirects++) {
            $this->assertFetchableUrl($currentUrl);

            try {
                $response = Http::timeout(15)
                    ->withOptions(['allow_redirects' => false])
                    ->accept('text/calendar')
                    ->get($currentUrl);
            } catch (ConnectionException) {
                throw new ExternalCalendarException('Der Kalender-Server ist nicht erreichbar.');
            }

            if (! $response->redirect() || ! $response->header('Location')) {
                break;
            }

            if ($redirects >= 3) {
                throw new ExternalCalendarException('Der Kalender-Link leitet zu oft weiter.');
            }

            $currentUrl = (string) UriResolver::resolve(
                new Uri($currentUrl),
                new Uri($response->header('Location'))
            );
        }

        if (! $response->successful()) {
            throw new ExternalCalendarException('Der Kalender-Server antwortete mit Fehler '.$response->status().'.');
        }

        try {
            $calendar = Reader::read($response->body(), Reader::OPTION_FORGIVING);
        } catch (ParseException) {
            throw new ExternalCalendarException('Der Link liefert keinen gültigen iCal-Kalender.');
        }

        if (! $calendar instanceof VCalendar) {
            throw new ExternalCalendarException('Der Link liefert keinen gültigen iCal-Kalender.');
        }

        return $calendar;
    }

    private function importEvents(ClubEventSource $source, VCalendar $calendar): int
    {
        $windowStart = CarbonImmutable::now()->subMonth()->startOfDay();
        $windowEnd = CarbonImmutable::now()->addYear();

        $expanded = $calendar->expand($windowStart, $windowEnd);

        $attributesByUid = [];

        foreach ($expanded->select('VEVENT') as $vevent) {
            $attributes = $this->mapEvent($vevent);

            if ($attributes !== null) {
                $attributesByUid[$attributes['external_uid']] = $attributes;
            }
        }

        DB::transaction(function () use ($source, $attributesByUid, $windowStart) {
            foreach ($attributesByUid as $uid => $attributes) {
                ClubEvent::query()->updateOrCreate(
                    ['sourceID' => $source->sourceID, 'external_uid' => $uid],
                    [...$attributes, 'rsvp_enabled' => $source->rsvp_enabled],
                );
            }

            // Was nicht mehr im Feed ist, entfällt – ältere Termine bleiben als Historie.
            ClubEvent::query()
                ->where('sourceID', $source->sourceID)
                ->where('starts_at', '>=', $this->toLocal($windowStart))
                ->whereNotIn('external_uid', array_keys($attributesByUid))
                ->delete();
        });

        return count($attributesByUid);
    }

    /**
     * @return array{external_uid: string, title: string, description: ?string, location: ?string, starts_at: Carbon, ends_at: ?Carbon, all_day: bool}|null
     */
    private function mapEvent(VEvent $vevent): ?array
    {
        if (! isset($vevent->DTSTART) || strtoupper((string) ($vevent->STATUS ?? '')) === 'CANCELLED') {
            return null;
        }

        $allDay = ! $vevent->DTSTART->hasTime();
        $start = $vevent->DTSTART->getDateTime();
        $end = isset($vevent->DTEND) ? $vevent->DTEND->getDateTime() : null;

        if ($end === null && isset($vevent->DURATION)) {
            $end = $start->add($vevent->DURATION->getDateInterval());
        }

        if ($allDay) {
            $startsAt = Carbon::parse($start->format('Y-m-d'))->startOfDay();

            // DTEND ist bei Ganztagsterminen exklusiv (Folgetag).
            $lastDay = $end ? Carbon::parse($end->format('Y-m-d'))->subDay() : null;
            $endsAt = $lastDay && $lastDay->gt($startsAt) ? $lastDay : null;
        } else {
            $startsAt = $this->toLocal($start);
            $endsAt = $end ? $this->toLocal($end) : null;
        }

        $uid = (string) ($vevent->UID ?? md5($vevent->serialize()));

        return [
            'external_uid' => Str::limit($uid, 230, '').'@'.$start->format('Ymd\THis'),
            'title' => Str::limit(trim((string) ($vevent->SUMMARY ?? '')) ?: 'Termin', 150, ''),
            'description' => trim((string) ($vevent->DESCRIPTION ?? '')) ?: null,
            'location' => Str::limit(trim((string) ($vevent->LOCATION ?? '')), 150, '') ?: null,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'all_day' => $allDay,
        ];
    }

    /**
     * Zeitpunkt → Wanduhrzeit der Vereinszeitzone (ohne Zeitzone gespeichert).
     */
    private function toLocal(DateTimeInterface $dateTime): Carbon
    {
        $local = CarbonImmutable::instance($dateTime)->setTimezone(config('app.club_timezone'));

        return Carbon::parse($local->format('Y-m-d H:i:s'));
    }

    /**
     * @return array<int, string>
     */
    private function resolveHost(string $host): array
    {
        $records = @dns_get_record($host, DNS_A | DNS_AAAA) ?: [];

        return array_values(array_filter(array_map(
            fn (array $record) => $record['ip'] ?? $record['ipv6'] ?? null,
            $records
        )));
    }
}
