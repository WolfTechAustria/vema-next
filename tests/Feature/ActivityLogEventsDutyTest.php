<?php

use App\Enums\EventResponseStatus;
use App\Livewire\DutyPlan\Absences;
use App\Livewire\DutyPlan\Index as DutyPlanIndex;
use App\Livewire\DutyPlan\RolesEditor;
use App\Livewire\DutyPlan\Volunteers;
use App\Livewire\Events\Index as EventsIndex;
use App\Livewire\MemberPortal\MyDuties;
use App\Livewire\MemberPortal\MyEvents;
use App\Models\ActivityLog;
use App\Models\ClubEvent;
use App\Models\ClubEventResponse;
use App\Models\ClubEventSource;
use App\Models\DutyPlan;
use App\Models\DutyPlanAbsence;
use App\Models\DutyPlanAssignment;
use App\Models\DutyPlanEvent;
use App\Models\DutyPlanRole;
use App\Models\DutyPlanVolunteer;
use App\Models\ExternalContact;
use App\Models\Member;
use App\Models\User;
use App\Services\DutyPlanAssignmentService;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

describe('club events', function () {
    beforeEach(function () {
        $this->actingAs(User::factory()->admin()->create());
    });

    it('records a new event with all fields', function () {
        Livewire::test(EventsIndex::class)
            ->set('title', 'Jahreshauptversammlung')
            ->set('location', 'Gasthof Post')
            ->set('startDate', '2026-11-14')
            ->set('startTime', '19:30')
            ->call('saveEvent')
            ->assertHasNoErrors();

        $entry = ActivityLog::query()->where('action', 'event.created')->sole();
        $changes = collect($entry->changes)->keyBy('field');

        expect($entry->description)->toBe('Termin „Jahreshauptversammlung“ (14.11.2026) wurde angelegt.')
            ->and($changes['Beginn']['new'])->toBe('14.11.2026 19:30')
            ->and($changes['Ort']['new'])->toBe('Gasthof Post')
            ->and($changes['Sichtbar für']['new'])->toBe('alle Mitglieder');
    });

    it('records changed fields including the visible members', function () {
        $event = ClubEvent::factory()->create(['title' => 'Sommerfest', 'location' => 'Festplatz', 'starts_at' => '2026-07-04 18:00:00', 'ends_at' => null, 'all_day' => false]);
        $member = Member::create(['gender' => 'm', 'name' => 'Max', 'surname' => 'Muster', 'active' => 1]);

        Livewire::test(EventsIndex::class)
            ->call('editEvent', $event->eventID)
            ->set('location', 'Vereinsheim')
            ->set('selectedMembers', [(string) $member->memberID])
            ->call('saveEvent')
            ->assertHasNoErrors();

        expect(loggedChanges('event.updated'))->toBe([
            'Ort' => ['field' => 'Ort', 'old' => 'Festplatz', 'new' => 'Vereinsheim', 'masked' => false],
            'Sichtbar für' => ['field' => 'Sichtbar für', 'old' => 'alle Mitglieder', 'new' => 'Max Muster', 'masked' => false],
        ]);
    });

    it('writes no entry when an event is saved unchanged', function () {
        $event = ClubEvent::factory()->create(['starts_at' => '2026-11-14 19:30:00', 'ends_at' => null, 'all_day' => false]);

        Livewire::test(EventsIndex::class)
            ->call('editEvent', $event->eventID)
            ->call('saveEvent')
            ->assertHasNoErrors();

        expect(ActivityLog::query()->count())->toBe(0);
    });

    it('records deleted events', function () {
        $event = ClubEvent::factory()->create(['title' => 'Vorstandssitzung', 'starts_at' => '2026-11-14 19:30:00']);

        Livewire::test(EventsIndex::class)->call('deleteEvent', $event->eventID);

        expect(ActivityLog::query()->where('action', 'event.deleted')->sole()->description)
            ->toBe('Termin „Vorstandssitzung“ (14.11.2026) wurde gelöscht.');
    });
});

describe('external calendars', function () {
    beforeEach(function () {
        $this->actingAs(User::factory()->admin()->create());
        Http::fake(fn () => Http::response('', 404));
    });

    it('masks the iCal link when a calendar is added or changed', function () {
        Livewire::test(EventsIndex::class)
            ->set('sourceName', 'Landesverband')
            ->set('sourceUrl', 'https://1.1.1.1/private/geheim-alt.ics')
            ->set('sourceActive', false)
            ->call('saveSource')
            ->assertHasNoErrors();

        $source = ClubEventSource::query()->sole();

        Livewire::test(EventsIndex::class)
            ->call('editSource', $source->sourceID)
            ->set('sourceUrl', 'https://1.1.1.1/private/geheim-neu.ics')
            ->set('sourceRsvpEnabled', true)
            ->call('saveSource')
            ->assertHasNoErrors();

        expect(loggedChanges('event.source_created')['iCal-Link'])->toBe(['field' => 'iCal-Link', 'old' => null, 'new' => null, 'masked' => true])
            ->and(loggedChanges('event.source_updated'))->toBe([
                'iCal-Link' => ['field' => 'iCal-Link', 'old' => null, 'new' => null, 'masked' => true],
                'Zu-/Absage möglich' => ['field' => 'Zu-/Absage möglich', 'old' => 'Nein', 'new' => 'Ja', 'masked' => false],
            ])
            ->and(json_encode(ActivityLog::query()->get()->toArray()))->not->toContain('geheim');
    });

    it('records a manual sync with its error and a deleted calendar', function () {
        $source = ClubEventSource::factory()->create(['name' => 'Gemeinde']);

        Livewire::test(EventsIndex::class)
            ->call('syncSource', $source->sourceID)
            ->call('deleteSource', $source->sourceID);

        expect(ActivityLog::query()->where('action', 'event.source_synced')->sole()->description)
            ->toContain('„Gemeinde“ konnte nicht synchronisiert werden: Der Kalender-Server antwortete mit Fehler 404.')
            ->and(ActivityLog::query()->where('action', 'event.source_deleted')->sole()->description)
            ->toBe('Externer Kalender „Gemeinde“ wurde mit 0 übernommenen Terminen entfernt.');
    });

    it('logs a failing scheduled sync only once per error', function () {
        auth()->logout();
        ClubEventSource::factory()->create(['name' => 'Gemeinde']);

        $this->artisan('events:sync-external')->assertSuccessful();
        $this->artisan('events:sync-external')->assertSuccessful();

        $entry = ActivityLog::query()->where('action', 'event.source_sync_failed')->sole();

        expect($entry->userID)->toBeNull()
            ->and($entry->description)->toContain('Fehler 404');
    });
});

describe('member portal', function () {
    it('records event responses with the previous answer', function () {
        $member = loginPortalMember();
        $event = ClubEvent::factory()->create(['title' => 'Sommerfest', 'starts_at' => now()->addDays(5)->setTime(18, 0), 'rsvp_enabled' => true]);
        ClubEventResponse::create(['eventID' => $event->eventID, 'memberID' => $member->memberID, 'status' => EventResponseStatus::Attending]);

        Livewire::test(MyEvents::class)
            ->call('respond', $event->eventID, 'attending')
            ->call('respond', $event->eventID, 'declined');

        $entry = ActivityLog::query()->where('action', 'portal.event_responded')->sole();

        expect($entry->memberAccountID)->not->toBeNull()
            ->and($entry->description)->toContain('hat für Termin „Sommerfest“')
            ->and($entry->description)->toEndWith('abgesagt.')
            ->and($entry->changes)->toBe([
                ['field' => 'Rückmeldung', 'old' => 'Zugesagt', 'new' => 'Abgesagt', 'masked' => false],
            ]);
    });

    it('records calendar subscriptions, reminders and renewed links without the token', function () {
        loginPortalMember();
        $source = ClubEventSource::factory()->create(['name' => 'Landesverband']);

        $oldLink = Livewire::test(MyEvents::class)
            ->call('toggleSourceSubscription', $source->sourceID)
            ->call('regenerateIcalLink')
            ->get('icalUrl');

        Livewire::test(MyDuties::class)->call('toggleReminder');

        expect(loggedChanges('portal.calendar_source_toggled')['Im Kalender-Abo']['new'])->toBe('Ja')
            ->and(loggedChanges('portal.calendar_link_renewed')['Kalender-Link']['masked'])->toBeTrue()
            ->and(loggedChanges('portal.duty_reminder_toggled')['E-Mail-Erinnerung an Dienste'])
            ->toMatchArray(['old' => 'Ja', 'new' => 'Nein'])
            ->and(json_encode(ActivityLog::query()->pluck('changes')))->not->toContain(basename($oldLink));
    });
});

describe('duty plan', function () {
    beforeEach(function () {
        $this->actingAs(User::factory()->admin()->create());
        $this->plan = DutyPlan::create(['name' => 'Saison 2026', 'date_from' => '2026-01-01', 'date_to' => '2026-12-31', 'exclude_holidays' => true]);
    });

    it('records changed plan fields and skips unchanged saves', function () {
        $component = Livewire::test(DutyPlanIndex::class)
            ->call('savePlan')
            ->set('planName', 'Saison 2026/27')
            ->set('dateTo', '2027-03-31')
            ->call('savePlan')
            ->assertHasNoErrors();

        expect(ActivityLog::query()->count())->toBe(1)
            ->and(loggedChanges('duty.plan_updated'))->toBe([
                'Name' => ['field' => 'Name', 'old' => 'Saison 2026', 'new' => 'Saison 2026/27', 'masked' => false],
                'Bis' => ['field' => 'Bis', 'old' => '31.12.2026', 'new' => '31.03.2027', 'masked' => false],
            ]);

        $component->call('deletePlan');

        expect(ActivityLog::query()->where('action', 'duty.plan_deleted')->sole()->description)
            ->toBe('Dienstplan „Saison 2026/27“ (01.01.2026 – 31.03.2027) wurde gelöscht.');
    });

    it('records a duplicated plan with counts', function () {
        DutyPlanRole::create(['planID' => $this->plan->planID, 'name' => 'Bar', 'weekday_mask' => 127]);
        $member = Member::create(['gender' => 'm', 'name' => 'Max', 'surname' => 'Muster', 'active' => 1]);
        DutyPlanVolunteer::create(['planID' => $this->plan->planID, 'memberID' => $member->memberID, 'active' => true, 'weekday_mask' => 127]);

        Livewire::test(DutyPlanIndex::class)->call('duplicatePlan');

        expect(ActivityLog::query()->where('action', 'duty.plan_duplicated')->sole()->description)
            ->toBe('Dienstplan „Saison 2026“ wurde als „Saison 2026 Kopie“ dupliziert (1 Dienstbezeichnungen, 1 Helfer übernommen).');
    });

    it('records generated events in a single entry', function () {
        DutyPlanRole::create(['planID' => $this->plan->planID, 'name' => 'Bar', 'weekday_mask' => 127]);

        Livewire::test(DutyPlanIndex::class)
            ->set('dateFrom', '2026-11-02')
            ->set('dateTo', '2026-11-08')
            ->call('generateEvents');

        expect(DutyPlanEvent::query()->count())->toBe(7)
            ->and(ActivityLog::query()->count())->toBe(1)
            ->and(ActivityLog::query()->where('action', 'duty.events_generated')->sole()->description)
            ->toBe('Termine für Dienstplan „Saison 2026“ (02.11.2026 – 08.11.2026) erzeugt: 7 neu, 0 aktualisiert, 0 entfernt, 0 Feiertagstermine ausgelassen.');
    });

    it('records automatic assignment in a single entry', function () {
        // Die Einteilung selbst ist nicht Gegenstand dieses Tests (sie braucht
        // Tabellen, die unter SQLite fehlen) – nur ihr Protokolleintrag.
        $this->mock(DutyPlanAssignmentService::class)
            ->shouldReceive('assign')
            ->once()
            ->andReturn(['assigned' => 12, 'unfilled' => 3, 'group_violations' => []]);

        Livewire::test(DutyPlanIndex::class)
            ->set('dateFrom', '2026-11-02')
            ->set('dateTo', '2026-11-08')
            ->call('autoAssign');

        expect(ActivityLog::query()->count())->toBe(1)
            ->and(ActivityLog::query()->where('action', 'duty.auto_assigned')->sole()->description)
            ->toBe('Automatische Einteilung im Dienstplan „Saison 2026“ für 02.11.2026 – 08.11.2026: 12 Helfer eingeteilt, 3 Plätze nicht besetzt.');
    });

    it('records a manually changed slot with old and new helper', function () {
        $role = DutyPlanRole::create(['planID' => $this->plan->planID, 'name' => 'Bar', 'weekday_mask' => 127]);
        $event = DutyPlanEvent::create(['planID' => $this->plan->planID, 'roleID' => $role->roleID, 'duty_date' => '2026-11-14', 'duty_name' => 'Bar', 'required_helpers' => 2]);
        $max = Member::create(['gender' => 'm', 'name' => 'Max', 'surname' => 'Muster', 'active' => 1]);
        $erika = Member::create(['gender' => 'w', 'name' => 'Erika', 'surname' => 'Beispiel', 'active' => 1]);
        DutyPlanAssignment::create(['eventID' => $event->eventID, 'slot_no' => 2, 'memberID' => $max->memberID]);

        Livewire::test(DutyPlanIndex::class)
            ->call('updateAssignment', $event->eventID, 2, 'member:'.$max->memberID)
            ->call('updateAssignment', $event->eventID, 2, 'member:'.$erika->memberID);

        $entry = ActivityLog::query()->where('action', 'duty.assignment_changed')->sole();

        expect($entry->description)->toBe('Einteilung für „Bar“ am 14.11.2026 wurde geändert.')
            ->and($entry->changes)->toBe([
                ['field' => 'Helfer 2', 'old' => 'Max Muster', 'new' => 'Erika Beispiel', 'masked' => false],
            ]);
    });

    it('records role changes, deactivation and deletion', function () {
        $role = DutyPlanRole::create(['planID' => $this->plan->planID, 'name' => 'Bar', 'required_helpers' => 1, 'weekday_mask' => 0b0000001]);

        Livewire::test(RolesEditor::class, ['planId' => $this->plan->planID])
            ->call('editRole', $role->roleID)
            ->set('required_helpers', 2)
            ->set('weekdays', ['1', '5'])
            ->call('saveRole')
            ->assertHasNoErrors()
            ->call('toggleRoleActive', $role->roleID)
            ->call('deleteRole', $role->roleID);

        expect(loggedChanges('duty.role_updated'))->toBe([
            'Benötigte Helfer' => ['field' => 'Benötigte Helfer', 'old' => '1', 'new' => '2', 'masked' => false],
            'Wochentage' => ['field' => 'Wochentage', 'old' => 'Mo', 'new' => 'Mo, Fr', 'masked' => false],
        ])
            ->and(ActivityLog::query()->where('action', 'duty.role_deactivated')->sole()->description)
            ->toBe('Dienstbezeichnung „Bar“ (Dienstplan „Saison 2026“) wurde deaktiviert.')
            ->and(ActivityLog::query()->where('action', 'duty.role_deleted')->exists())->toBeTrue();
    });

    it('records helpers added, removed and their weekdays', function () {
        $member = Member::create(['gender' => 'm', 'name' => 'Max', 'surname' => 'Muster', 'active' => 1]);
        $contact = ExternalContact::create(['name' => 'Erika', 'surname' => 'Beispiel', 'email' => 'erika@example.test']);

        Livewire::test(Volunteers::class)
            ->call('toggleVolunteer', $member->memberID)
            ->call('toggleWeekday', $member->memberID, 7)
            ->call('toggleVolunteer', $member->memberID)
            ->call('toggleExternalVolunteer', $contact->externalContactID);

        expect(ActivityLog::query()->where('action', 'duty.volunteer_added')->pluck('description')->all())->toBe([
            'Mitglied "Max Muster" wurde im Dienstplan „Saison 2026“ als Helfer aufgenommen.',
            'Externer Helfer "Beispiel Erika" wurde im Dienstplan „Saison 2026“ als Helfer aufgenommen.',
        ])
            ->and(ActivityLog::query()->where('action', 'duty.volunteer_removed')->count())->toBe(1)
            ->and(loggedChanges('duty.volunteer_weekdays_changed')['Verfügbare Wochentage'])
            ->toMatchArray(['old' => 'Mo, Di, Mi, Do, Fr, Sa, So', 'new' => 'Mo, Di, Mi, Do, Fr, Sa']);
    });

    it('records helpers copied from another plan in a single entry', function () {
        $previous = DutyPlan::create(['name' => 'Saison 2025', 'date_from' => '2025-01-01', 'date_to' => '2025-12-31']);
        foreach (['Max', 'Moritz'] as $name) {
            $member = Member::create(['gender' => 'm', 'name' => $name, 'surname' => 'Muster', 'active' => 1]);
            DutyPlanVolunteer::create(['planID' => $previous->planID, 'memberID' => $member->memberID, 'active' => true, 'weekday_mask' => 127]);
        }

        Livewire::test(Volunteers::class)
            ->call('selectPlan', $this->plan->planID)
            ->call('copyVolunteersFromPlan', $previous->planID);

        expect(ActivityLog::query()->count())->toBe(1)
            ->and(ActivityLog::query()->where('action', 'duty.volunteers_copied')->sole()->description)
            ->toBe('Helferliste von Dienstplan „Saison 2025“ in Dienstplan „Saison 2026“ übernommen: 2 neu, 0 bereits vorhanden.');
    });

    it('records created and deleted absences', function () {
        $member = Member::create(['gender' => 'm', 'name' => 'Max', 'surname' => 'Muster', 'active' => 1]);

        Livewire::test(Absences::class)
            ->set('memberId', $member->memberID)
            ->set('dateFrom', '2026-11-01')
            ->set('dateTo', '2026-11-05')
            ->set('note', 'Urlaub')
            ->call('save')
            ->assertHasNoErrors()
            ->call('delete', DutyPlanAbsence::query()->sole()->absenceID);

        expect(loggedChanges('duty.absence_created'))->toMatchArray([
            'Helfer' => ['field' => 'Helfer', 'old' => null, 'new' => 'Max Muster', 'masked' => false],
            'Notiz' => ['field' => 'Notiz', 'old' => null, 'new' => 'Urlaub', 'masked' => false],
        ])
            ->and(ActivityLog::query()->where('action', 'duty.absence_deleted')->sole()->description)
            ->toBe('Abwesenheit von "Max Muster" (01.11.2026 – 05.11.2026) wurde gelöscht.');
    });
});
