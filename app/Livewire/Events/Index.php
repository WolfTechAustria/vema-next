<?php

namespace App\Livewire\Events;

use App\Enums\EventResponseStatus;
use App\Models\ClubEvent;
use App\Models\ClubEventSource;
use App\Models\Member;
use App\Services\ExternalCalendarException;
use App\Services\ExternalCalendarSyncService;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
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

    /**
     * Mitglieder, die den Termin sehen; leer = alle.
     *
     * @var array<int, string>
     */
    public array $selectedMembers = [];

    public ?int $editingEventID = null;

    public ?int $viewingResponsesEventID = null;

    public bool $showPast = false;

    /**
     * Anzahl der angezeigten anstehenden Termine („Mehr anzeigen“ erhöht sie).
     */
    public int $visibleUpcomingCount = self::UPCOMING_PAGE_SIZE;

    private const UPCOMING_PAGE_SIZE = 5;

    public string $sourceName = '';

    public string $sourceUrl = '';

    public bool $sourceRsvpEnabled = false;

    public bool $sourceActive = true;

    public ?int $editingSourceID = null;

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
            'selectedMembers' => ['array'],
            'selectedMembers.*' => ['integer', Rule::exists('tb_members', 'memberID')],
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
            $event = ClubEvent::query()->whereNull('sourceID')->findOrFail($this->editingEventID);
            $event->update($attributes);

            session()->flash('success', 'Termin wurde aktualisiert.');
        } else {
            $event = ClubEvent::create($attributes);

            session()->flash('success', 'Termin wurde angelegt.');
        }

        // Keine Auswahl = für alle Mitglieder sichtbar.
        $event->members()->sync(array_map('intval', $this->selectedMembers));

        $this->resetForm();
    }

    public function editEvent(int $eventID): void
    {
        $event = ClubEvent::query()->whereNull('sourceID')->findOrFail($eventID);

        $this->editingEventID = $event->eventID;
        $this->title = $event->title;
        $this->description = $event->description ?? '';
        $this->location = $event->location ?? '';
        $this->allDay = $event->all_day;
        $this->rsvpEnabled = $event->rsvp_enabled;
        $this->selectedMembers = $event->members()
            ->pluck('tb_members.memberID')
            ->map(fn ($memberID) => (string) $memberID)
            ->all();
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
        ClubEvent::query()->whereNull('sourceID')->findOrFail($eventID)->delete();

        if ($this->editingEventID === $eventID) {
            $this->resetForm();
        }

        if ($this->viewingResponsesEventID === $eventID) {
            $this->viewingResponsesEventID = null;
        }

        session()->flash('success', 'Termin wurde gelöscht.');
    }

    public function saveSource(ExternalCalendarSyncService $syncService): void
    {
        $validated = $this->validate([
            'sourceName' => ['required', 'string', 'max:150'],
            'sourceUrl' => ['required', 'string', 'max:2000'],
            'sourceRsvpEnabled' => ['boolean'],
            'sourceActive' => ['boolean'],
        ], [], [
            'sourceName' => 'Beschriftung',
            'sourceUrl' => 'iCal-Link',
        ]);

        try {
            $syncService->assertFetchableUrl($validated['sourceUrl']);
        } catch (ExternalCalendarException $exception) {
            $this->addError('sourceUrl', $exception->getMessage());

            return;
        }

        $attributes = [
            'name' => $validated['sourceName'],
            'url' => trim($validated['sourceUrl']),
            'rsvp_enabled' => $this->sourceRsvpEnabled,
            'active' => $this->sourceActive,
        ];

        if ($this->editingSourceID) {
            $source = ClubEventSource::findOrFail($this->editingSourceID);
            $source->update($attributes);

            // Zu-/Absage gilt sofort auch für bereits übernommene Termine.
            $source->events()->update(['rsvp_enabled' => $source->rsvp_enabled]);
        } else {
            $source = ClubEventSource::create($attributes);
        }

        if ($source->active) {
            $syncService->sync($source);
        }

        $this->resetSourceForm();

        if ($source->last_sync_error) {
            session()->flash('warning', 'Kalender gespeichert, aber nicht synchronisiert: '.$source->last_sync_error);
        } else {
            session()->flash('success', 'Kalender „'.$source->name.'“ wurde gespeichert.');
        }
    }

    public function editSource(int $sourceID): void
    {
        $source = ClubEventSource::findOrFail($sourceID);

        $this->editingSourceID = $source->sourceID;
        $this->sourceName = $source->name;
        $this->sourceUrl = $source->url;
        $this->sourceRsvpEnabled = $source->rsvp_enabled;
        $this->sourceActive = $source->active;

        $this->resetValidation();
    }

    public function cancelSourceEdit(): void
    {
        $this->resetSourceForm();
        $this->resetValidation();
    }

    public function syncSource(int $sourceID, ExternalCalendarSyncService $syncService): void
    {
        $source = ClubEventSource::findOrFail($sourceID);

        $count = $syncService->sync($source);

        if ($source->last_sync_error) {
            session()->flash('error', $source->name.': '.$source->last_sync_error);
        } else {
            session()->flash('success', $source->name.': '.$count.' Termine synchronisiert.');
        }
    }

    public function deleteSource(int $sourceID): void
    {
        ClubEventSource::findOrFail($sourceID)->delete();

        if ($this->editingSourceID === $sourceID) {
            $this->resetSourceForm();
        }

        session()->flash('success', 'Kalender und seine Termine wurden entfernt.');
    }

    public function showMoreEvents(): void
    {
        $this->visibleUpcomingCount += self::UPCOMING_PAGE_SIZE;
    }

    public function updatedShowPast(): void
    {
        $this->visibleUpcomingCount = self::UPCOMING_PAGE_SIZE;
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
            'selectedMembers',
            'editingEventID',
        ]);
    }

    private function resetSourceForm(): void
    {
        $this->reset([
            'sourceName',
            'sourceUrl',
            'sourceRsvpEnabled',
            'sourceActive',
            'editingSourceID',
        ]);
    }

    public function render(): View
    {
        $eventsQuery = ClubEvent::query()
            ->when(
                $this->showPast,
                fn ($query) => $query->past()->orderByDesc('starts_at'),
                fn ($query) => $query->upcoming()->orderBy('starts_at'),
            );

        $totalEvents = $eventsQuery->count();

        $events = $eventsQuery
            ->when(! $this->showPast, fn ($query) => $query->limit($this->visibleUpcomingCount))
            ->with(['source', 'responses.member', 'members'])
            ->get();

        $responseOverview = null;

        if ($this->viewingResponsesEventID) {
            $event = $events->firstWhere('eventID', $this->viewingResponsesEventID);

            if ($event) {
                $responseOverview = [
                    'event' => $event,
                    'attending' => $event->responses
                        ->where('status', EventResponseStatus::Attending)
                        ->sortBy(fn ($response) => $response->member?->surname),
                    'declined' => $event->responses
                        ->where('status', EventResponseStatus::Declined)
                        ->sortBy(fn ($response) => $response->member?->surname),
                ];
            }
        }

        return view('livewire.events.index', [
            'events' => $events,
            'members' => Member::query()
                ->where('active', true)
                ->orderBy('surname')
                ->orderBy('name')
                ->get(['memberID', 'name', 'surname']),
            'hiddenEventsCount' => $totalEvents - $events->count(),
            'responseOverview' => $responseOverview,
            'sources' => ClubEventSource::query()->withCount('events')->orderBy('name')->get(),
        ])->layout('layouts.app', [
            'title' => 'Termine | VEMA',
            'heading' => 'Termine',
        ]);
    }
}
