<?php

use App\Enums\EventResponseStatus;
use App\Livewire\Events\Index as EventsIndex;
use App\Livewire\MemberPortal\MyDuties as MemberPortalMyDuties;
use App\Livewire\MemberPortal\MyEvents;
use App\Models\ClubEvent;
use App\Models\ClubEventResponse;
use App\Models\DutyPlan;
use App\Models\DutyPlanAssignment;
use App\Models\DutyPlanEvent;
use App\Models\DutyPlanRole;
use App\Models\Member;
use App\Models\MemberDutySettings;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Livewire;

/**
 * „Termin 01“ morgen, „Termin 02“ übermorgen, …
 */
function createNumberedUpcomingEvents(int $count): void
{
    foreach (range(1, $count) as $number) {
        $start = now()->addDays($number)->setTime(19, 0);

        ClubEvent::factory()->create([
            'title' => sprintf('Termin %02d', $number),
            'description' => null,
            'location' => null,
            'starts_at' => $start,
            'ends_at' => $start->copy()->addHours(2),
        ]);
    }
}

describe('staff', function () {
    beforeEach(function () {
        $this->actingAs(User::factory()->admin()->create());
    });

    it('creates an event with start and end time', function () {
        Livewire::test(EventsIndex::class)
            ->set('title', 'Jahreshauptversammlung')
            ->set('location', 'Gasthof Post')
            ->set('startDate', '2026-11-14')
            ->set('startTime', '19:30')
            ->set('endTime', '22:00')
            ->call('saveEvent')
            ->assertHasNoErrors();

        $event = ClubEvent::query()->sole();

        expect($event->title)->toBe('Jahreshauptversammlung')
            ->and($event->location)->toBe('Gasthof Post')
            ->and($event->starts_at->format('Y-m-d H:i'))->toBe('2026-11-14 19:30')
            ->and($event->ends_at->format('Y-m-d H:i'))->toBe('2026-11-14 22:00')
            ->and($event->all_day)->toBeFalse()
            ->and($event->rsvp_enabled)->toBeTrue();
    });

    it('creates an all-day event without a start time', function () {
        Livewire::test(EventsIndex::class)
            ->set('title', 'Sommerfest')
            ->set('allDay', true)
            ->set('startDate', '2026-07-04')
            ->set('endDate', '2026-07-05')
            ->call('saveEvent')
            ->assertHasNoErrors();

        $event = ClubEvent::query()->sole();

        expect($event->all_day)->toBeTrue()
            ->and($event->starts_at->format('Y-m-d H:i'))->toBe('2026-07-04 00:00')
            ->and($event->ends_at->format('Y-m-d'))->toBe('2026-07-05');
    });

    it('requires title, date and start time', function () {
        Livewire::test(EventsIndex::class)
            ->call('saveEvent')
            ->assertHasErrors(['title' => 'required', 'startDate' => 'required', 'startTime' => 'required']);

        expect(ClubEvent::query()->count())->toBe(0);
    });

    it('rejects an end before the start', function () {
        Livewire::test(EventsIndex::class)
            ->set('title', 'Sitzung')
            ->set('startDate', '2026-11-14')
            ->set('startTime', '19:30')
            ->set('endTime', '18:00')
            ->call('saveEvent')
            ->assertHasErrors('endTime');

        expect(ClubEvent::query()->count())->toBe(0);
    });

    it('updates an existing event', function () {
        $event = ClubEvent::factory()->create(['title' => 'Alt']);

        Livewire::test(EventsIndex::class)
            ->call('editEvent', $event->eventID)
            ->assertSet('title', 'Alt')
            ->set('title', 'Neu')
            ->call('saveEvent')
            ->assertHasNoErrors()
            ->assertSet('editingEventID', null);

        expect($event->fresh()->title)->toBe('Neu')
            ->and(ClubEvent::query()->count())->toBe(1);
    });

    it('deletes an event together with its responses', function () {
        $event = ClubEvent::factory()->create();
        $member = Member::create(['gender' => 'w', 'name' => 'Anna', 'surname' => 'Beispiel', 'active' => 1]);
        ClubEventResponse::create(['eventID' => $event->eventID, 'memberID' => $member->memberID, 'status' => EventResponseStatus::Attending]);

        Livewire::test(EventsIndex::class)
            ->call('deleteEvent', $event->eventID);

        expect(ClubEvent::query()->count())->toBe(0)
            ->and(ClubEventResponse::query()->count())->toBe(0);
    });

    it('lists who attends and who declined', function () {
        $event = ClubEvent::factory()->create();
        $attending = Member::create(['gender' => 'w', 'name' => 'Anna', 'surname' => 'Zusager', 'active' => 1]);
        $declining = Member::create(['gender' => 'm', 'name' => 'Bernd', 'surname' => 'Absager', 'active' => 1]);
        Member::create(['gender' => 'm', 'name' => 'Carl', 'surname' => 'Schweiger', 'active' => 1]);

        ClubEventResponse::create(['eventID' => $event->eventID, 'memberID' => $attending->memberID, 'status' => EventResponseStatus::Attending]);
        ClubEventResponse::create(['eventID' => $event->eventID, 'memberID' => $declining->memberID, 'status' => EventResponseStatus::Declined]);

        Livewire::test(EventsIndex::class)
            ->call('toggleResponses', $event->eventID)
            ->assertSee('Zugesagt (1)')
            ->assertSee('Abgesagt (1)')
            ->assertSee('Zusager')
            ->assertSee('Absager')
            ->assertDontSee('Keine Rückmeldung')
            ->assertDontSee('Schweiger');
    });

    it('shows the next five upcoming events and more on demand', function () {
        createNumberedUpcomingEvents(7);

        Livewire::test(EventsIndex::class)
            ->assertSee('Termin 05')
            ->assertDontSee('Termin 06')
            ->assertSee('Mehr anzeigen (2 weitere)')
            ->call('showMoreEvents')
            ->assertSee('Termin 07')
            ->assertDontSee('Mehr anzeigen');
    });

    it('separates upcoming from past events', function () {
        ClubEvent::factory()->create(['title' => 'Kommt noch']);
        ClubEvent::factory()->past()->create(['title' => 'Schon vorbei']);

        Livewire::test(EventsIndex::class)
            ->assertSee('Kommt noch')
            ->assertDontSee('Schon vorbei')
            ->toggle('showPast')
            ->assertSee('Schon vorbei')
            ->assertDontSee('Kommt noch');
    });
});

describe('member portal', function () {
    it('shows the next five upcoming events and more on demand', function () {
        loginPortalMember();
        createNumberedUpcomingEvents(7);

        Livewire::test(MyEvents::class)
            ->assertSee('Termin 05')
            ->assertDontSee('Termin 06')
            ->assertSee('Mehr anzeigen (2 weitere)')
            ->call('showMoreEvents')
            ->assertSee('Termin 07')
            ->assertDontSee('Mehr anzeigen');
    });

    it('shows upcoming events only', function () {
        loginPortalMember();
        ClubEvent::factory()->create(['title' => 'Kommt noch']);
        ClubEvent::factory()->past()->create(['title' => 'Schon vorbei']);

        Livewire::test(MyEvents::class)
            ->assertSee('Kommt noch')
            ->assertDontSee('Schon vorbei');
    });

    it('lets a member attend and change their mind', function () {
        $member = loginPortalMember();
        $event = ClubEvent::factory()->create();

        $component = Livewire::test(MyEvents::class)
            ->call('respond', $event->eventID, 'attending');

        expect(ClubEventResponse::query()->sole()->status)->toBe(EventResponseStatus::Attending);

        $component->call('respond', $event->eventID, 'declined');

        $response = ClubEventResponse::query()->sole();

        expect($response->status)->toBe(EventResponseStatus::Declined)
            ->and($response->memberID)->toBe($member->memberID);
    });

    it('refuses responses for events without rsvp or in the past', function (string $state) {
        loginPortalMember();
        $event = ClubEvent::factory()->{$state}()->create();

        expect(fn () => Livewire::test(MyEvents::class)->call('respond', $event->eventID, 'attending'))
            ->toThrow(ModelNotFoundException::class);

        expect(ClubEventResponse::query()->count())->toBe(0);
    })->with(['withoutRsvp', 'past']);

    it('regenerates the calendar link', function () {
        $member = loginPortalMember();

        $component = Livewire::test(MyEvents::class);
        $oldUrl = $component->get('icalUrl');

        $component->call('regenerateIcalLink');

        expect($component->get('icalUrl'))->not->toBe($oldUrl)
            ->and($component->get('icalUrl'))->toContain(MemberDutySettings::find($member->memberID)->ical_token);
    });
});

describe('calendar feed', function () {
    it('serves upcoming events but hides declined ones', function () {
        $member = Member::create(['gender' => 'm', 'name' => 'Max', 'surname' => 'Muster', 'active' => 1]);
        $token = MemberDutySettings::forMember($member)->getOrCreateIcalToken();

        ClubEvent::factory()->create(['title' => 'Sommerfest', 'location' => 'Festplatz']);
        $declined = ClubEvent::factory()->create(['title' => 'Vorstandssitzung']);
        ClubEventResponse::create(['eventID' => $declined->eventID, 'memberID' => $member->memberID, 'status' => EventResponseStatus::Declined]);

        $this->get(route('member-calendar.ical', $token))
            ->assertOk()
            ->assertHeader('Content-Type', 'text/calendar; charset=utf-8')
            ->assertSee('SUMMARY:Sommerfest', false)
            ->assertSee('Festplatz', false)
            ->assertDontSee('Vorstandssitzung', false);
    });

    it('serves duties and club events through the same link', function () {
        $member = loginPortalMember();

        $plan = DutyPlan::create(['name' => 'Saison', 'date_from' => now()->startOfYear(), 'date_to' => now()->endOfYear()]);
        $role = DutyPlanRole::create(['planID' => $plan->planID, 'name' => 'Schießstandaufsicht']);
        $dutyEvent = DutyPlanEvent::create([
            'planID' => $plan->planID,
            'roleID' => $role->roleID,
            'duty_date' => now()->addDays(3)->toDateString(),
            'duty_name' => 'Schießstandaufsicht',
            'required_helpers' => 1,
            'start_time' => '18:00',
            'end_time' => '21:00',
        ]);
        DutyPlanAssignment::create(['eventID' => $dutyEvent->eventID, 'slot_no' => 1, 'memberID' => $member->memberID]);

        ClubEvent::factory()->create(['title' => 'Sommerfest']);

        $dutiesLink = Livewire::test(MemberPortalMyDuties::class)->get('icalUrl');
        $eventsLink = Livewire::test(MyEvents::class)->get('icalUrl');

        expect($eventsLink)->toBe($dutiesLink);

        $this->get($eventsLink)
            ->assertOk()
            ->assertSee('SUMMARY:Schießstandaufsicht', false)
            ->assertSee('SUMMARY:Sommerfest', false);
    });

    it('rejects unknown tokens and inactive members', function () {
        $this->get(route('member-calendar.ical', 'unbekannt'))->assertNotFound();

        $member = Member::create(['gender' => 'm', 'name' => 'Max', 'surname' => 'Inaktiv', 'active' => 0]);
        $token = MemberDutySettings::forMember($member)->getOrCreateIcalToken();

        $this->get(route('member-calendar.ical', $token))->assertNotFound();
    });
});
