<?php

use App\Enums\EventResponseStatus;
use App\Livewire\Admin\Users\Index as UsersIndex;
use App\Livewire\Events\Attendance;
use App\Livewire\Events\AttendanceStatistics;
use App\Livewire\MemberPortal\MyEvents;
use App\Models\ActivityLog;
use App\Models\ClubEvent;
use App\Models\ClubEventAttendance;
use App\Models\ClubEventResponse;
use App\Models\Member;
use App\Models\User;
use Livewire\Livewire;

function attendanceMember(string $name, bool $active = true): Member
{
    return Member::create(['gender' => 'm', 'name' => $name, 'surname' => 'Probe', 'active' => (int) $active]);
}

function pastEvent(string $title = 'Probe', ?string $startsAt = null): ClubEvent
{
    $start = $startsAt ? now()->parse($startsAt) : now()->subDay()->setTime(19, 0);

    return ClubEvent::factory()->create([
        'title' => $title,
        'starts_at' => $start,
        'ends_at' => $start->copy()->addHours(2),
    ]);
}

describe('staff', function () {
    beforeEach(function () {
        $this->actingAs(User::factory()->admin()->create(), 'web');
    });

    it('prefills the attending members and records the attendance', function () {
        $anna = attendanceMember('Anna');
        $bert = attendanceMember('Bert');
        $carl = attendanceMember('Carl');
        attendanceMember('Ausgetreten', active: false);
        $event = pastEvent();

        ClubEventResponse::create(['eventID' => $event->eventID, 'memberID' => $anna->memberID, 'status' => EventResponseStatus::Attending]);
        ClubEventResponse::create(['eventID' => $event->eventID, 'memberID' => $bert->memberID, 'status' => EventResponseStatus::Declined]);

        Livewire::test(Attendance::class, ['event' => $event])
            ->assertSet('presentMemberIDs', [$anna->memberID])
            ->assertSee('Carl Probe')
            ->assertDontSee('Ausgetreten Probe')
            ->set('presentMemberIDs', [(string) $anna->memberID, (string) $carl->memberID])
            ->call('save')
            ->assertRedirect(route('events.attendance', $event));

        expect($event->fresh()->attendance_taken_at)->not->toBeNull()
            ->and(ClubEventAttendance::query()->where('eventID', $event->eventID)->pluck('present', 'memberID')->all())
            ->toEqual([$anna->memberID => true, $bert->memberID => false, $carl->memberID => true])
            ->and(ActivityLog::query()->where('action', 'event.attendance_recorded')->sole()->description)
            ->toContain('2 von 3 anwesend');

        // Beim erneuten Öffnen gilt die gespeicherte Anwesenheit, nicht mehr die Zusagen.
        Livewire::test(Attendance::class, ['event' => $event->fresh()])
            ->assertSet('presentMemberIDs', [$anna->memberID, $carl->memberID]);
    });

    it('lists only the selected members of a restricted event', function () {
        $anna = attendanceMember('Anna');
        attendanceMember('Bert');
        $event = pastEvent();
        $event->members()->attach($anna->memberID);

        Livewire::test(Attendance::class, ['event' => $event])
            ->assertSee('Anna Probe')
            ->assertDontSee('Bert Probe');
    });

    it('cannot record future events', function () {
        $event = ClubEvent::factory()->create(['starts_at' => now()->addDays(3)]);

        $this->get(route('events.attendance', $event))->assertNotFound();
    });

    it('calculates the yearly quote per member', function () {
        $anna = attendanceMember('Anna');
        $bert = attendanceMember('Bert');
        $year = now()->year - 1;

        foreach (['01-15', '02-15', '03-15', '04-15'] as $index => $day) {
            $event = pastEvent('Probe '.$index, $year.'-'.$day.' 19:00');

            Livewire::test(Attendance::class, ['event' => $event])
                ->set('presentMemberIDs', $index === 0 ? [$anna->memberID, $bert->memberID] : [$anna->memberID])
                ->call('save');
        }

        // Ohne erfasste Anwesenheit zählt ein Termin nicht.
        pastEvent('Nicht erfasst', $year.'-05-15 19:00');

        Livewire::test(AttendanceStatistics::class)
            ->set('year', $year)
            ->assertViewHas('rows', fn ($rows) => $rows->map(fn ($row) => [$row['member']->name, $row['present'], $row['total'], $row['percent']])->all()
                === [['Anna', 4, 4, 100], ['Bert', 1, 4, 25]])
            ->assertSee('Erfasste Termine (4)');
    });
});

describe('permissions', function () {
    it('allows only admins and the attendance role', function () {
        $event = pastEvent();
        $staff = User::factory()->create();
        $this->actingAs($staff, 'web');

        $this->get(route('events.attendance', $event))->assertForbidden();
        $this->get(route('events.attendance-statistics'))->assertForbidden();

        $admin = User::factory()->admin()->create();
        $this->actingAs($admin, 'web');

        Livewire::test(UsersIndex::class)->call('toggleAttendance', $staff->id);
        expect($staff->fresh()->hasRole('anwesenheit'))->toBeTrue();

        $this->actingAs($staff->fresh(), 'web');
        $this->get(route('events.attendance', $event))->assertOk();
        $this->get(route('events.attendance-statistics'))->assertOk();
    });
});

describe('member portal', function () {
    it('shows the own attendance quote of the current year', function () {
        $member = loginPortalMember();
        $event = pastEvent('Frühjahrsprobe', now()->year.'-01-10 19:00');
        $missed = pastEvent('Herbstprobe', now()->year.'-01-20 19:00');

        foreach ([$event, $missed] as $recorded) {
            ClubEventAttendance::create(['eventID' => $recorded->eventID, 'memberID' => $member->memberID, 'present' => $recorded->is($event)]);
            $recorded->forceFill(['attendance_taken_at' => now()])->save();
        }

        Livewire::test(MyEvents::class)
            ->assertSee('Meine Anwesenheit '.now()->year)
            ->assertSee('1 von 2')
            ->assertSee('50 %')
            ->assertSee('Frühjahrsprobe')
            ->assertSee('Nicht anwesend');
    });
});
