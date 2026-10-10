<?php

namespace App\Livewire\Events;

use App\Enums\EventResponseStatus;
use App\Models\ClubEvent;
use App\Models\Member;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class Index extends Component
{
    public string $title = '';

    public string $description = '';

    public string $location = '';

    public string $startDate = '';

    public string $startTime = '';

    public string $endDate = '';

    public string $endTime = '';

    public bool $allDay = false;

    public bool $rsvpEnabled = true;

    public ?int $editingEventID = null;

    public ?int $viewingResponsesEventID = null;

    public bool $showPast = false;

    public function saveEvent(): void
    {
        $validated = $this->validate([
            'title' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
            'location' => ['nullable', 'string', 'max:150'],
            'startDate' => ['required', 'date_format:Y-m-d'],
            'startTime' => [$this->allDay ? 'nullable' : 'required', 'date_format:H:i'],
            'endDate' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:startDate'],
            'endTime' => ['nullable', 'date_format:H:i'],
            'allDay' => ['boolean'],
            'rsvpEnabled' => ['boolean'],
        ], [], [
            'title' => 'Titel',
            'startDate' => 'Datum',
            'startTime' => 'Beginn',
            'endDate' => 'Enddatum',
            'endTime' => 'Ende',
        ]);

        $startsAt = $this->allDay
            ? Carbon::parse($validated['startDate'])->startOfDay()
            : Carbon::parse($validated['startDate'].' '.$validated['startTime']);

        $endsAt = $this->resolveEndsAt($startsAt, $validated);

        if ($endsAt && $endsAt->lt($startsAt)) {
            $this->addError('endTime', 'Das Ende muss nach dem Beginn liegen.');

            return;
        }

        $attributes = [
            'title' => $validated['title'],
            'description' => $validated['description'] ?: null,
            'location' => $validated['location'] ?: null,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'all_day' => $this->allDay,
            'rsvp_enabled' => $this->rsvpEnabled,
        ];

        if ($this->editingEventID) {
            ClubEvent::findOrFail($this->editingEventID)->update($attributes);

            session()->flash('success', 'Termin wurde aktualisiert.');
        } else {
            ClubEvent::create($attributes);

            session()->flash('success', 'Termin wurde angelegt.');
        }

        $this->resetForm();
    }

    public function editEvent(int $eventID): void
    {
        $event = ClubEvent::findOrFail($eventID);

        $this->editingEventID = $event->eventID;
        $this->title = $event->title;
        $this->description = $event->description ?? '';
        $this->location = $event->location ?? '';
        $this->allDay = $event->all_day;
        $this->rsvpEnabled = $event->rsvp_enabled;
        $this->startDate = $event->starts_at->format('Y-m-d');
        $this->startTime = $event->all_day ? '' : $event->starts_at->format('H:i');
        $this->endDate = $event->ends_at && ! $event->ends_at->isSameDay($event->starts_at)
            ? $event->ends_at->format('Y-m-d')
            : '';
        $this->endTime = $event->ends_at && ! $event->all_day ? $event->ends_at->format('H:i') : '';

        $this->resetValidation();
    }

    public function cancelEdit(): void
    {
        $this->resetForm();
        $this->resetValidation();
    }

    public function deleteEvent(int $eventID): void
    {
        ClubEvent::findOrFail($eventID)->delete();

        if ($this->editingEventID === $eventID) {
            $this->resetForm();
        }

        if ($this->viewingResponsesEventID === $eventID) {
            $this->viewingResponsesEventID = null;
        }

        session()->flash('success', 'Termin wurde gelöscht.');
    }

    public function toggleResponses(int $eventID): void
    {
        $this->viewingResponsesEventID = $this->viewingResponsesEventID === $eventID ? null : $eventID;
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function resolveEndsAt(Carbon $startsAt, array $validated): ?Carbon
    {
        $endDate = $validated['endDate'] ?: null;
        $endTime = $validated['endTime'] ?: null;

        if ($this->allDay) {
            return $endDate ? Carbon::parse($endDate)->startOfDay() : null;
        }

        if (! $endTime) {
            return null;
        }

        return Carbon::parse(($endDate ?? $startsAt->format('Y-m-d')).' '.$endTime);
    }

    private function resetForm(): void
    {
        $this->reset([
            'title',
            'description',
            'location',
            'startDate',
            'startTime',
            'endDate',
            'endTime',
            'allDay',
            'rsvpEnabled',
            'editingEventID',
        ]);
    }

    public function render(): View
    {
        $events = ClubEvent::query()
            ->when(
                $this->showPast,
                fn ($query) => $query->past()->orderByDesc('starts_at'),
                fn ($query) => $query->upcoming()->orderBy('starts_at'),
            )
            ->with('responses.member')
            ->get();

        $responseOverview = null;

        if ($this->viewingResponsesEventID) {
            $event = $events->firstWhere('eventID', $this->viewingResponsesEventID);

            if ($event) {
                $respondedMemberIDs = $event->responses->pluck('memberID')->all();

                $responseOverview = [
                    'event' => $event,
                    'attending' => $event->responses
                        ->where('status', EventResponseStatus::Attending)
                        ->sortBy(fn ($response) => $response->member?->surname),
                    'declined' => $event->responses
                        ->where('status', EventResponseStatus::Declined)
                        ->sortBy(fn ($response) => $response->member?->surname),
                    'pending' => Member::query()
                        ->where('active', true)
                        ->whereNotIn('memberID', $respondedMemberIDs)
                        ->orderBy('surname')
                        ->orderBy('name')
                        ->get(),
                ];
            }
        }

        return view('livewire.events.index', [
            'events' => $events,
            'responseOverview' => $responseOverview,
        ])->layout('layouts.app', [
            'title' => 'Termine | VEMA',
            'heading' => 'Termine',
        ]);
    }
}
