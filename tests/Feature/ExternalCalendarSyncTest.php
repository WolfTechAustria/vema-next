<?php

use App\Enums\EventResponseStatus;
use App\Livewire\Events\Index as EventsIndex;
use App\Livewire\MemberPortal\MyEvents;
use App\Models\ClubEvent;
use App\Models\ClubEventResponse;
use App\Models\ClubEventSource;
use App\Models\Member;
use App\Models\MemberEventSettings;
use App\Models\User;
use App\Services\ExternalCalendarException;
use App\Services\ExternalCalendarSyncService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

beforeEach(function () {
    $this->travelTo(now()->setDate(2026, 10, 10)->setTime(12, 0));

    // Eine Fake-Antwort, die pro Test ausgetauscht werden kann (Http::fake
    // ein zweites Mal aufzurufen ersetzt die erste Antwort nicht).
    $this->externalCalendarResponse = ['', 404, []];
    Http::fake(fn () => Http::response(...$this->externalCalendarResponse));
});

/**
 * Kalender mit Einzeltermin (Wiener Zeit), UTC-Termin, zweitägigem
 * Ganztagstermin und einer wöchentlichen Serie mit drei Terminen.
 *
 * @param  array<int, string>  $extraEvents
 */
function externalCalendarBody(array $extraEvents = [], bool $includeMeeting = true): string
{
    $events = [];

    if ($includeMeeting) {
        $events[] = <<<'ICS'
BEGIN:VEVENT
UID:meeting-1@verband.example
DTSTAMP:20261001T080000Z
DTSTART;TZID=Europe/Vienna:20261114T193000
DTEND;TZID=Europe/Vienna:20261114T220000
SUMMARY:Bezirkssitzung
LOCATION:Gasthof Post
DESCRIPTION:Tagesordnung folgt
END:VEVENT
ICS;
    }

    $events[] = <<<'ICS'
BEGIN:VEVENT
UID:utc-1@verband.example
DTSTAMP:20261001T080000Z
DTSTART:20270115T180000Z
DTEND:20270115T200000Z
SUMMARY:Winterempfang
END:VEVENT
ICS;

    $events[] = <<<'ICS'
BEGIN:VEVENT
UID:fest-1@verband.example
DTSTAMP:20261001T080000Z
DTSTART;VALUE=DATE:20261205
DTEND;VALUE=DATE:20261207
SUMMARY:Adventmarkt
END:VEVENT
ICS;

    $events[] = <<<'ICS'
BEGIN:VEVENT
UID:training@verband.example
DTSTAMP:20261001T080000Z
DTSTART;TZID=Europe/Vienna:20261020T190000
DTEND;TZID=Europe/Vienna:20261020T210000
RRULE:FREQ=WEEKLY;COUNT=3
SUMMARY:Bezirkstraining
END:VEVENT
ICS;

    return implode("\r\n", [
        'BEGIN:VCALENDAR',
        'VERSION:2.0',
        'PRODID:-//Test//DE',
        ...$events,
        ...$extraEvents,
        'END:VCALENDAR',
    ]);
}

/**
 * @param  array<string, string>  $headers
 */
function fakeExternalCalendar(string $body, int $status = 200, array $headers = ['Content-Type' => 'text/calendar']): void
{
    test()->externalCalendarResponse = [$body, $status, $headers];
}

describe('sync', function () {
    it('imports single, utc, all-day and recurring events in club time', function () {
        fakeExternalCalendar(externalCalendarBody());
        $source = ClubEventSource::factory()->create();

        $count = app(ExternalCalendarSyncService::class)->sync($source);

        expect($count)->toBe(6)
            ->and($source->fresh()->last_sync_error)->toBeNull()
            ->and($source->fresh()->last_synced_at)->not->toBeNull();

        $meeting = ClubEvent::query()->where('title', 'Bezirkssitzung')->sole();
        expect($meeting->starts_at->format('Y-m-d H:i'))->toBe('2026-11-14 19:30')
            ->and($meeting->ends_at->format('Y-m-d H:i'))->toBe('2026-11-14 22:00')
            ->and($meeting->location)->toBe('Gasthof Post')
            ->and($meeting->description)->toBe('Tagesordnung folgt')
            ->and($meeting->sourceID)->toBe($source->sourceID);

        // 18:00 UTC im Winter = 19:00 Wiener Zeit
        expect(ClubEvent::query()->where('title', 'Winterempfang')->sole()->starts_at->format('Y-m-d H:i'))
            ->toBe('2027-01-15 19:00');

        $fest = ClubEvent::query()->where('title', 'Adventmarkt')->sole();
        expect($fest->all_day)->toBeTrue()
            ->and($fest->starts_at->format('Y-m-d'))->toBe('2026-12-05')
            ->and($fest->ends_at->format('Y-m-d'))->toBe('2026-12-06');

        expect(ClubEvent::query()->where('title', 'Bezirkstraining')->orderBy('starts_at')->pluck('starts_at')->map->format('Y-m-d H:i')->all())
            ->toBe(['2026-10-20 19:00', '2026-10-27 19:00', '2026-11-03 19:00']);
    });

    it('updates instead of duplicating and keeps member responses', function () {
        fakeExternalCalendar(externalCalendarBody());
        $source = ClubEventSource::factory()->withRsvp()->create();
        $syncService = app(ExternalCalendarSyncService::class);
        $syncService->sync($source);

        $meeting = ClubEvent::query()->where('title', 'Bezirkssitzung')->sole();
        $member = Member::create(['gender' => 'm', 'name' => 'Max', 'surname' => 'Muster', 'active' => 1]);
        ClubEventResponse::create(['eventID' => $meeting->eventID, 'memberID' => $member->memberID, 'status' => EventResponseStatus::Attending]);

        fakeExternalCalendar(str_replace('Gasthof Post', 'Gemeindesaal', externalCalendarBody()));
        $syncService->sync($source);

        expect(ClubEvent::query()->count())->toBe(6)
            ->and($meeting->fresh()->location)->toBe('Gemeindesaal')
            ->and($meeting->fresh()->rsvp_enabled)->toBeTrue()
            ->and(ClubEventResponse::query()->count())->toBe(1);
    });

    it('removes upcoming events that disappeared from the feed but keeps old history', function () {
        fakeExternalCalendar(externalCalendarBody());
        $source = ClubEventSource::factory()->create();
        $old = ClubEvent::factory()->past()->create([
            'sourceID' => $source->sourceID,
            'external_uid' => 'old@verband.example',
            'starts_at' => now()->subMonths(3),
        ]);
        $syncService = app(ExternalCalendarSyncService::class);
        $syncService->sync($source);

        fakeExternalCalendar(externalCalendarBody(includeMeeting: false));
        $syncService->sync($source);

        expect(ClubEvent::query()->where('title', 'Bezirkssitzung')->exists())->toBeFalse()
            ->and(ClubEvent::query()->count())->toBe(6)
            ->and($old->fresh())->not->toBeNull();
    });

    it('skips cancelled events', function () {
        fakeExternalCalendar(externalCalendarBody([<<<'ICS'
BEGIN:VEVENT
UID:abgesagt@verband.example
DTSTAMP:20261001T080000Z
DTSTART;TZID=Europe/Vienna:20261120T190000
STATUS:CANCELLED
SUMMARY:Fällt aus
END:VEVENT
ICS]));
        $source = ClubEventSource::factory()->create();

        app(ExternalCalendarSyncService::class)->sync($source);

        expect(ClubEvent::query()->where('title', 'Fällt aus')->exists())->toBeFalse();
    });

    it('records errors and leaves existing events untouched', function (string $body, int $status, string $message) {
        fakeExternalCalendar(externalCalendarBody());
        $source = ClubEventSource::factory()->create();
        $syncService = app(ExternalCalendarSyncService::class);
        $syncService->sync($source);

        fakeExternalCalendar($body, $status);
        $syncService->sync($source);

        expect($source->fresh()->last_sync_error)->toContain($message)
            ->and(ClubEvent::query()->count())->toBe(6);
    })->with([
        'server error' => ['', 500, 'Fehler 500'],
        'not a calendar' => ['<html>Login</html>', 200, 'keinen gültigen iCal-Kalender'],
    ]);

    it('rejects internal addresses and other schemes', function (string $url) {
        expect(fn () => app(ExternalCalendarSyncService::class)->assertFetchableUrl($url))
            ->toThrow(ExternalCalendarException::class);
    })->with([
        'http://127.0.0.1/calendar.ics',
        'http://192.168.1.10/calendar.ics',
        'http://10.0.0.5/calendar.ics',
        'http://[::1]/calendar.ics',
        'ftp://1.1.1.1/calendar.ics',
        'file:///etc/passwd',
    ]);

    it('accepts webcal links', function () {
        expect(app(ExternalCalendarSyncService::class)->normalizeUrl('webcal://1.1.1.1/a.ics'))
            ->toBe('https://1.1.1.1/a.ics');
    });

    it('does not follow redirects to internal addresses', function () {
        fakeExternalCalendar('', 302, ['Location' => 'http://127.0.0.1/secret.ics']);
        $source = ClubEventSource::factory()->create();

        app(ExternalCalendarSyncService::class)->sync($source);

        expect($source->fresh()->last_sync_error)->toContain('interne Adressen');
        Http::assertSentCount(1);
    });

    it('syncs active sources from the scheduled command', function () {
        fakeExternalCalendar(externalCalendarBody());
        $active = ClubEventSource::factory()->create();
        ClubEventSource::factory()->inactive()->create();

        $this->artisan('events:sync-external')->assertSuccessful();

        expect(ClubEvent::query()->where('sourceID', $active->sourceID)->count())->toBe(6)
            ->and(ClubEvent::query()->count())->toBe(6);
    });

    it('stores the link encrypted', function () {
        $source = ClubEventSource::factory()->create(['url' => 'https://1.1.1.1/private/geheim.ics']);

        expect(DB::table('tb_event_sources')->value('url'))->not->toContain('geheim')
            ->and($source->fresh()->url)->toBe('https://1.1.1.1/private/geheim.ics');
    });
});

describe('staff', function () {
    beforeEach(function () {
        $this->actingAs(User::factory()->admin()->create());
    });

    it('adds a calendar and syncs it right away', function () {
        fakeExternalCalendar(externalCalendarBody());

        Livewire::test(EventsIndex::class)
            ->set('sourceName', 'Landesverband')
            ->set('sourceUrl', 'webcal://1.1.1.1/verband.ics')
            ->set('sourceRsvpEnabled', true)
            ->call('saveSource')
            ->assertHasNoErrors()
            ->assertSee('Landesverband')
            ->assertSee('Bezirkssitzung');

        $source = ClubEventSource::query()->sole();

        expect($source->name)->toBe('Landesverband')
            ->and($source->rsvp_enabled)->toBeTrue()
            ->and($source->events()->count())->toBe(6)
            ->and($source->events()->where('rsvp_enabled', false)->exists())->toBeFalse();
    });

    it('requires a label and a public link', function () {
        Livewire::test(EventsIndex::class)
            ->set('sourceUrl', 'http://127.0.0.1/cal.ics')
            ->call('saveSource')
            ->assertHasErrors(['sourceName' => 'required'])
            ->set('sourceName', 'Intern')
            ->call('saveSource')
            ->assertHasErrors('sourceUrl');

        expect(ClubEventSource::query()->count())->toBe(0);
    });

    it('applies a changed rsvp setting to already imported events', function () {
        $source = ClubEventSource::factory()->create();
        $event = ClubEvent::factory()->create(['sourceID' => $source->sourceID, 'external_uid' => 'x', 'rsvp_enabled' => false]);
        fakeExternalCalendar(externalCalendarBody());

        Livewire::test(EventsIndex::class)
            ->call('editSource', $source->sourceID)
            ->set('sourceRsvpEnabled', true)
            ->set('sourceActive', false)
            ->call('saveSource')
            ->assertHasNoErrors();

        expect($event->fresh()->rsvp_enabled)->toBeTrue();
        Http::assertNothingSent();
    });

    it('cannot edit or delete imported events', function (string $method) {
        $source = ClubEventSource::factory()->create();
        $event = ClubEvent::factory()->create(['sourceID' => $source->sourceID, 'external_uid' => 'x']);

        expect(fn () => Livewire::test(EventsIndex::class)->call($method, $event->eventID))
            ->toThrow(ModelNotFoundException::class);

        expect($event->fresh())->not->toBeNull();
    })->with(['editEvent', 'deleteEvent']);

    it('deletes a calendar with its events', function () {
        $source = ClubEventSource::factory()->create();
        ClubEvent::factory()->count(2)->create(['sourceID' => $source->sourceID, 'external_uid' => fn () => fake()->uuid()]);
        ClubEvent::factory()->create(['title' => 'Eigener Termin']);

        Livewire::test(EventsIndex::class)
            ->call('deleteSource', $source->sourceID);

        expect(ClubEventSource::query()->count())->toBe(0)
            ->and(ClubEvent::query()->pluck('title')->all())->toBe(['Eigener Termin']);
    });
});

describe('member portal and calendar feed', function () {
    it('shows imported events with their label and respects the rsvp setting', function () {
        loginPortalMember();
        $withRsvp = ClubEventSource::factory()->withRsvp()->create(['name' => 'Landesverband']);
        $readOnly = ClubEventSource::factory()->create(['name' => 'Gemeinde']);
        $inactive = ClubEventSource::factory()->inactive()->create(['name' => 'Alt']);

        $attendable = ClubEvent::factory()->create(['title' => 'Landestreffen', 'sourceID' => $withRsvp->sourceID, 'external_uid' => 'a', 'rsvp_enabled' => true]);
        $readOnlyEvent = ClubEvent::factory()->create(['title' => 'Gemeindefest', 'sourceID' => $readOnly->sourceID, 'external_uid' => 'b', 'rsvp_enabled' => false]);
        ClubEvent::factory()->create(['title' => 'Versteckt', 'sourceID' => $inactive->sourceID, 'external_uid' => 'c']);

        $component = Livewire::test(MyEvents::class)
            ->assertSee('Landestreffen')
            ->assertSee('Landesverband')
            ->assertSee('Gemeindefest')
            ->assertDontSee('Versteckt')
            ->call('respond', $attendable->eventID, 'attending');

        expect(ClubEventResponse::query()->count())->toBe(1);

        expect(fn () => $component->call('respond', $readOnlyEvent->eventID, 'attending'))
            ->toThrow(ModelNotFoundException::class);
    });

    it('includes imported events in the feed only after opting in', function () {
        $member = loginPortalMember();
        $source = ClubEventSource::factory()->create(['name' => 'Landesverband']);
        ClubEvent::factory()->create(['title' => 'Landestreffen', 'sourceID' => $source->sourceID, 'external_uid' => 'a']);
        ClubEvent::factory()->create(['title' => 'Vereinsfest']);
        $token = MemberEventSettings::forMember($member)->getOrCreateIcalToken();

        $this->get(route('events.ical', $token))
            ->assertSee('Vereinsfest')
            ->assertDontSee('Landestreffen');

        Livewire::test(MyEvents::class)
            ->assertSee('Landesverband')
            ->call('toggleSourceSubscription', $source->sourceID);

        $this->get(route('events.ical', $token))
            ->assertSee('Vereinsfest')
            ->assertSee('Landestreffen');

        Livewire::test(MyEvents::class)
            ->call('toggleSourceSubscription', $source->sourceID);

        $this->get(route('events.ical', $token))
            ->assertDontSee('Landestreffen');
    });
});
