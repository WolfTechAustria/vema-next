<?php

namespace App\Livewire\Events;

use App\Models\ClubEvent;
use App\Models\Member;
use App\Services\ActivityLogger;
use App\Services\EventAttendance;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Component;

/**
 * Anwesenheitsliste eines Vereinstermins (anwesend ja/nein je Mitglied).
 */
class Attendance extends Component
{
    public ClubEvent $event;

    /**
     * Angehakte Mitglieder.
     *
     * @var list<int>
     */
    public array $presentMemberIDs = [];

    public string $search = '';

    public function mount(ClubEvent $event, EventAttendance $attendance): void
    {
        abort_unless(EventAttendance::canRecord($event), 404);

        $this->event = $event;
        $this->presentMemberIDs = $attendance->presentMemberIDs($event);
    }

    /**
     * Alle (gefilterten) Mitglieder an- bzw. abhaken.
     */
    public function markAll(bool $present, EventAttendance $attendance): void
    {
        $visibleIDs = $this->filteredMembers($attendance)->map(fn ($member) => (int) $member->memberID)->all();

        $this->presentMemberIDs = $present
            ? array_values(array_unique([...$this->normalizedPresentIDs(), ...$visibleIDs]))
            : array_values(array_diff($this->normalizedPresentIDs(), $visibleIDs));
    }

    public function save(EventAttendance $attendance): void
    {
        $wasTaken = $this->event->attendance_taken_at !== null;
        $before = $wasTaken ? $attendance->presentMemberIDs($this->event) : [];

        $result = $attendance->record($this->event, $this->normalizedPresentIDs());
        $this->event->refresh();

        $after = $attendance->presentMemberIDs($this->event);

        if (! $wasTaken || array_diff($before, $after) !== [] || array_diff($after, $before) !== []) {
            ActivityLogger::log(
                $wasTaken ? 'event.attendance_updated' : 'event.attendance_recorded',
                'Anwesenheit für Termin '.$this->event->activityName().' wurde '.($wasTaken ? 'geändert' : 'erfasst')
                    .': '.$result['present'].' von '.$result['total'].' anwesend.',
                'ClubEvent',
                $this->event->eventID,
            );
        }

        session()->flash('success', 'Anwesenheit gespeichert: '.$result['present'].' von '.$result['total'].' Mitgliedern anwesend.');

        $this->redirectRoute('events.attendance', $this->event, navigate: true);
    }

    /**
     * @return list<int>
     */
    private function normalizedPresentIDs(): array
    {
        return array_values(array_unique(array_map('intval', $this->presentMemberIDs)));
    }

    /**
     * @return Collection<int, Member>
     */
    private function filteredMembers(EventAttendance $attendance): Collection
    {
        $members = $attendance->eligibleMembers($this->event);
        $search = mb_strtolower(trim($this->search));

        if ($search === '') {
            return $members;
        }

        return $members->filter(fn ($member) => str_contains(mb_strtolower($member->full_name), $search))->values();
    }

    public function render(EventAttendance $attendance): View
    {
        $allMembers = $attendance->eligibleMembers($this->event);
        $presentIDs = $this->normalizedPresentIDs();

        return view('livewire.events.attendance', [
            'members' => $this->filteredMembers($attendance),
            'totalCount' => $allMembers->count(),
            'presentCount' => $allMembers->filter(fn ($member) => in_array((int) $member->memberID, $presentIDs, true))->count(),
        ])->layout('layouts.app', [
            'title' => 'Anwesenheit | VEMA',
            'heading' => 'Anwesenheit',
        ]);
    }
}
