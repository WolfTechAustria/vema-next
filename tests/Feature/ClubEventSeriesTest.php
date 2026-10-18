<?php

use App\Enums\EventResponseStatus;
use App\Livewire\Events\Index as EventsIndex;
use App\Models\ActivityLog;
use App\Models\ClubEvent;
use App\Models\ClubEventResponse;
use App\Models\ClubEventSeries;
use App\Models\Member;
use App\Models\User;
use App\Services\ClubEventSeriesService;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

/**
 * @param  array<string, mixed>  $rule
 * @return array<int, string>
 */
function seriesDates(string $start, array $rule): array
{
    return array_map(
        fn ($date) => $date->format('Y-m-d'),
        app(ClubEventSeriesService::class)->occurrenceDates(Carbon::parse($start), $rule + ['interval' => 1])
    );
}

/**
 * Wöchentliche Probe jeden Dienstag ab 03.11.2026, 19:30–21:30.
 */
function createWeeklyRehearsals(int $count = 6): void
{
    Livewire::test(EventsIndex::class)
        ->set('title', 'Probe')
        ->set('startDate', '2026-11-03')
        ->set('startTime', '19:30')
        ->set('endTime', '21:30')
        ->set('repeat', 'weekly')
        ->set('repeatEnd', 'count')
        ->set('repeatCount', (string) $count)
        ->call('saveEvent')
        ->assertHasNoErrors();
}

/**
 * @return array<int, string>
 */
function rehearsalDays(): array
{
    return ClubEvent::query()->orderBy('starts_at')->get()
        ->map(fn (ClubEvent $event) => $event->starts_at->format('Y-m-d'))
        ->all();
}

describe('rules', function () {
    it('repeats weekly on several weekdays every second week', function () {
        expect(seriesDates('2026-11-03', [
            'frequency' => 'weekly',
            'interval' => 2,
            'weekdays' => [2, 4],
            'until' => '2026-11-30',
        ]))->toBe(['2026-11-03', '2026-11-05', '2026-11-17', '2026-11-19']);
    });

    it('repeats monthly on the same day and skips months without it', function () {
        expect(seriesDates('2027-01-31', [
            'frequency' => 'monthly_day',
            'count' => 3,
        ]))->toBe(['2027-01-31', '2027-03-31', '2027-05-31']);
    });

    it('repeats monthly on the nth and on the last weekday', function () {
        expect(seriesDates('2026-11-06', [
            'frequency' => 'monthly_weekday',
            'month_week' => 1,
            'count' => 3,
        ]))->toBe(['2026-11-06', '2026-12-04', '2027-01-01'])
            ->and(seriesDates('2026-11-27', [
                'frequency' => 'monthly_weekday',
                'month_week' => -1,
                'count' => 3,
            ]))->toBe(['2026-11-27', '2026-12-25', '2027-01-29']);
    });

    it('leaves out excluded days', function () {
        expect(seriesDates('2026-11-03', [
            'frequency' => 'weekly',
            'weekdays' => [2],
            'count' => 3,
            'excluded_dates' => ['2026-11-10'],
        ]))->toBe(['2026-11-03', '2026-11-17']);
    });

    it('describes the rule in words', function () {
        expect(ClubEventSeries::describeRule(['frequency' => 'weekly', 'interval' => 2, 'weekdays' => [4, 2], 'until' => '2027-01-19']))
            ->toBe('alle 2 Wochen am Di, Do bis 19.01.2027')
            ->and(ClubEventSeries::describeRule(['frequency' => 'monthly_weekday', 'interval' => 1, 'month_week' => -1, 'count' => 10], Carbon::parse('2026-11-27')))
            ->toBe('monatlich am letzten Freitag, 10-mal');
    });
});

describe('staff', function () {
    beforeEach(function () {
        $this->actingAs(User::factory()->admin()->create());
    });

    it('creates a weekly series as single events', function () {
        $member = Member::create(['gender' => 'm', 'name' => 'Max', 'surname' => 'Bläser', 'active' => 1]);

        Livewire::test(EventsIndex::class)
            ->set('title', 'Probe')
            ->set('startDate', '2026-11-03')
            ->set('startTime', '19:30')
            ->set('endTime', '21:30')
            ->set('repeat', 'weekly')
            ->assertSet('repeatWeekdays', ['2'])
            ->set('repeatUntil', '2026-12-01')
            ->set('selectedMembers', [(string) $member->memberID])
            ->assertSee('5 Termine')
            ->call('saveEvent')
            ->assertHasNoErrors()
            ->assertSee('↻ Serie');

        $events = ClubEvent::query()->with('members')->orderBy('starts_at')->get();

        expect(rehearsalDays())->toBe(['2026-11-03', '2026-11-10', '2026-11-17', '2026-11-24', '2026-12-01'])
            ->and($events->pluck('seriesID')->unique()->all())->toBe([ClubEventSeries::query()->sole()->seriesID])
            ->and($events->every(fn (ClubEvent $event) => $event->ends_at->format('H:i') === '21:30'))->toBeTrue()
            ->and($events->every(fn (ClubEvent $event) => $event->members->pluck('memberID')->all() === [$member->memberID]))->toBeTrue()
            ->and(ActivityLog::query()->where('action', 'event.series_created')->sole()->description)
            ->toContain('5 Terminen');
    });

    it('limits a series to 150 events', function () {
        Livewire::test(EventsIndex::class)
            ->set('title', 'Training')
            ->set('startDate', '2026-11-02')
            ->set('startTime', '18:00')
            ->set('repeat', 'weekly')
            ->set('repeatWeekdays', ['1', '3', '5'])
            ->set('repeatUntil', '2028-12-31')
            ->call('saveEvent')
            ->assertHasErrors('repeatUntil');

        expect(ClubEvent::query()->count())->toBe(0);
    });

    it('changes only the selected event', function () {
        createWeeklyRehearsals(3);
        $second = ClubEvent::query()->orderBy('starts_at')->skip(1)->first();

        Livewire::test(EventsIndex::class)
            ->call('editEvent', $second->eventID)
            ->assertSet('editScope', 'single')
            ->set('title', 'Generalprobe')
            ->call('saveEvent')
            ->assertHasNoErrors();

        expect(ClubEvent::query()->orderBy('starts_at')->pluck('title')->all())->toBe(['Probe', 'Generalprobe', 'Probe'])
            ->and($second->fresh()->seriesID)->not->toBeNull();
    });

    it('changes this and all following events and keeps their responses', function () {
        createWeeklyRehearsals(6);
        $events = ClubEvent::query()->orderBy('starts_at')->get();
        $member = Member::create(['gender' => 'w', 'name' => 'Eva', 'surname' => 'Horn', 'active' => 1]);
        ClubEventResponse::create(['eventID' => $events[4]->eventID, 'memberID' => $member->memberID, 'status' => EventResponseStatus::Attending]);

        Livewire::test(EventsIndex::class)
            ->call('editEvent', $events[3]->eventID)
            ->set('editScope', 'following')
            ->assertSet('repeatUntil', '2026-12-08')
            ->set('title', 'Probe (neue Zeit)')
            ->set('startTime', '20:00')
            ->set('endTime', '22:00')
            ->call('saveEvent')
            ->assertHasNoErrors();

        $after = ClubEvent::query()->orderBy('starts_at')->get();
        $oldSeries = $events[0]->series->fresh();

        expect($after->pluck('title')->all())->toBe(['Probe', 'Probe', 'Probe', 'Probe (neue Zeit)', 'Probe (neue Zeit)', 'Probe (neue Zeit)'])
            ->and($after[0]->starts_at->format('H:i'))->toBe('19:30')
            ->and($after[4]->starts_at->format('Y-m-d H:i'))->toBe('2026-12-01 20:00')
            ->and($after[4]->eventID)->toBe($events[4]->eventID)
            ->and($after[4]->responses()->count())->toBe(1)
            ->and($oldSeries->until->format('Y-m-d'))->toBe('2026-11-23')
            ->and($after[3]->seriesID)->not->toBe($oldSeries->seriesID)
            ->and(ActivityLog::query()->where('action', 'event.series_updated')->exists())->toBeTrue();
    });

    it('moves the following events to another weekday', function () {
        createWeeklyRehearsals(3);
        $first = ClubEvent::query()->orderBy('starts_at')->first();

        Livewire::test(EventsIndex::class)
            ->call('editEvent', $first->eventID)
            ->set('editScope', 'following')
            ->set('startDate', '2026-11-04')
            ->set('repeatWeekdays', ['3'])
            ->set('repeatUntil', '2026-11-18')
            ->call('saveEvent')
            ->assertHasNoErrors();

        expect(rehearsalDays())->toBe(['2026-11-04', '2026-11-11', '2026-11-18'])
            ->and(ClubEventSeries::query()->count())->toBe(1);
    });

    it('does not bring back a single deleted event when the series changes', function () {
        createWeeklyRehearsals(4);
        $events = ClubEvent::query()->orderBy('starts_at')->get();

        Livewire::test(EventsIndex::class)
            ->call('deleteEvent', $events[1]->eventID)
            ->call('editEvent', $events[0]->eventID)
            ->set('editScope', 'following')
            ->set('location', 'Musikheim')
            ->call('saveEvent')
            ->assertHasNoErrors();

        expect(rehearsalDays())->toBe(['2026-11-03', '2026-11-17', '2026-11-24'])
            ->and(ClubEvent::query()->pluck('location')->unique()->all())->toBe(['Musikheim']);
    });

    it('deletes this and all following events', function () {
        createWeeklyRehearsals(4);
        $events = ClubEvent::query()->orderBy('starts_at')->get();

        Livewire::test(EventsIndex::class)
            ->call('deleteFollowingEvents', $events[2]->eventID);

        expect(rehearsalDays())->toBe(['2026-11-03', '2026-11-10'])
            ->and(ActivityLog::query()->where('action', 'event.series_deleted')->exists())->toBeTrue();

        Livewire::test(EventsIndex::class)
            ->call('deleteFollowingEvents', $events[0]->eventID);

        expect(ClubEvent::query()->count())->toBe(0)
            ->and(ClubEventSeries::query()->count())->toBe(0);
    });
});
