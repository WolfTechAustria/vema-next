<?php

namespace App\Livewire\Events;

use App\Enums\EventResponseStatus;
use App\Models\ClubEvent;
use App\Models\ClubEventSeries;
use App\Models\ClubEventSource;
use App\Models\Member;
use App\Services\ActivityLogger;
use App\Services\ClubEventSeriesService;
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

    public bool $showOnHomepage = false;

    /**
     * Mitglieder, die den Termin sehen; leer = alle.
     *
     * @var array<int, string>
     */
    public array $selectedMembers = [];

    public ?int $editingEventID = null;

    /**
     * Wiederholung: none, weekly, monthly_day, monthly_weekday oder
     * monthly_last_weekday.
     */
    public string $repeat = 'none';

    public string $repeatInterval = '1';

    /**
     * ISO-Wochentage (1 = Montag) bei wöchentlicher Wiederholung.
     *
     * @var array<int, string>
     */
    public array $repeatWeekdays = [];

    /**
     * Serienende: until (bis Datum) oder count (Anzahl Termine).
     */
    public string $repeatEnd = 'until';

    public string $repeatUntil = '';

    public string $repeatCount = '10';

    /**
     * Beim Bearbeiten eines Serientermins: single (nur dieser) oder
     * following (dieser und alle folgenden).
     */
    public string $editScope = 'single';

    public ?int $editingSeriesID = null;

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

    public bool $sourceShowOnHomepage = false;

    public ?int $editingSourceID = null;

    public function saveEvent(ClubEventSeriesService $seriesService): void
    {
        $savesSeries = $this->savesSeries();

        $validated = $this->validate([
            ...$this->seriesRules($savesSeries),
            'title' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
            'location' => ['nullable', 'string', 'max:150'],
            'startDate' => ['required', 'date_format:Y-m-d'],
            'startTime' => [$this->allDay ? 'nullable' : 'required', 'date_format:H:i'],
            'endDate' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:startDate'],
            'endTime' => ['nullable', 'date_format:H:i'],
            'allDay' => ['boolean'],
            'rsvpEnabled' => ['boolean'],
            'showOnHomepage' => ['boolean'],
            'selectedMembers' => ['array'],
            'selectedMembers.*' => ['integer', Rule::exists('tb_members', 'memberID')],
        ], [], [
            'title' => 'Titel',
            'startDate' => 'Datum',
            'startTime' => 'Beginn',
            'endDate' => 'Enddatum',
            'endTime' => 'Ende',
            'repeat' => 'Wiederholung',
            'repeatInterval' => 'Intervall',
            'repeatWeekdays' => 'Wochentage',
            'repeatUntil' => 'Enddatum der Serie',
            'repeatCount' => 'Anzahl der Termine',
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
            'show_on_homepage' => $this->showOnHomepage,
        ];

        if ($savesSeries) {
            $this->saveSeries($seriesService, $attributes);

            return;
        }

        if ($this->editingEventID) {
            $event = ClubEvent::query()->whereNull('sourceID')->findOrFail($this->editingEventID);
            $before = $event->activitySnapshot();
            $event->update($attributes);

            session()->flash('success', 'Termin wurde aktualisiert.');
        } else {
            $before = null;
            $event = ClubEvent::create($attributes);

            session()->flash('success', 'Termin wurde angelegt.');
        }

        // Keine Auswahl = für alle Mitglieder sichtbar.
        $event->members()->sync(array_map('intval', $this->selectedMembers));

        $this->logEventSaved($event->fresh(), $before);

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
        $this->showOnHomepage = $event->show_on_homepage;
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

        $this->fillSeriesFields($event);

        $this->resetValidation();
    }

    /**
     * Regel der Serie für „diesen und alle folgenden“ vorbelegen; das Ende
     * wird immer als Datum des letzten Termins angeboten.
     */
    private function fillSeriesFields(ClubEvent $event): void
    {
        $this->editScope = 'single';
        $this->editingSeriesID = $event->seriesID;

        $series = $event->series;

        if (! $series) {
            $this->repeat = 'none';

            return;
        }

        $this->repeat = match (true) {
            $series->frequency === ClubEventSeries::FREQUENCY_WEEKLY => 'weekly',
            $series->frequency === ClubEventSeries::FREQUENCY_MONTHLY_DAY => 'monthly_day',
            $series->month_week === -1 => 'monthly_last_weekday',
            default => 'monthly_weekday',
        };
        $this->repeatInterval = (string) $series->interval;
        $this->repeatWeekdays = array_map('strval', $series->weekdays ?? []);
        $this->repeatEnd = 'until';
        $this->repeatUntil = Carbon::parse($series->events()->max('starts_at'))->format('Y-m-d');
    }

    public function updatedRepeat(): void
    {
        $this->defaultWeekdayFromStartDate();
    }

    public function updatedStartDate(): void
    {
        $this->defaultWeekdayFromStartDate();

        // Passt die gewählte Regel nicht mehr zum Datum (z. B. „letzter Dienstag“), die nächstliegende wählen.
        if (! array_key_exists($this->repeat, $this->repeatOptions())) {
            $this->repeat = array_key_exists('monthly_weekday', $this->repeatOptions()) ? 'monthly_weekday' : 'monthly_last_weekday';
        }
    }

    /**
     * Bei wöchentlicher Wiederholung ohne Auswahl den Wochentag des Datums
     * vorbelegen.
     */
    private function defaultWeekdayFromStartDate(): void
    {
        if ($this->repeat !== 'weekly' || $this->repeatWeekdays !== [] || ! $this->validStartDate()) {
            return;
        }

        $this->repeatWeekdays = [(string) $this->validStartDate()->dayOfWeekIso];
    }

    private function validStartDate(): ?Carbon
    {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->startDate)) {
            return null;
        }

        try {
            return Carbon::createFromFormat('Y-m-d', $this->startDate)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Neuer Serientermin oder Änderung „diesen und alle folgenden“.
     */
    private function savesSeries(): bool
    {
        if ($this->editingEventID) {
            return $this->editingSeriesID !== null && $this->editScope === 'following';
        }

        return $this->repeat !== 'none';
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function seriesRules(bool $savesSeries): array
    {
        if (! $savesSeries) {
            return [];
        }

        return [
            'repeat' => ['required', Rule::in(['weekly', 'monthly_day', 'monthly_weekday', 'monthly_last_weekday'])],
            'repeatInterval' => ['required', 'integer', 'min:1', 'max:12'],
            'repeatWeekdays' => [$this->repeat === 'weekly' ? 'required' : 'nullable', 'array'],
            'repeatWeekdays.*' => ['integer', 'between:1,7'],
            'repeatEnd' => ['required', Rule::in(['until', 'count'])],
            'repeatUntil' => [$this->repeatEnd === 'until' ? 'required' : 'nullable', 'date_format:Y-m-d', 'after_or_equal:startDate'],
            'repeatCount' => [$this->repeatEnd === 'count' ? 'required' : 'nullable', 'integer', 'min:2', 'max:'.ClubEventSeriesService::MAX_OCCURRENCES],
        ];
    }

    /**
     * @return array{frequency: string, interval: int, weekdays: array<int, int>|null, month_week: int|null, until: string|null, count: int|null, excluded_dates: array<int, string>}
     */
    private function repeatRule(Carbon $start): array
    {
        $monthWeek = intdiv($start->day - 1, 7) + 1;

        return [
            'frequency' => match ($this->repeat) {
                'weekly' => ClubEventSeries::FREQUENCY_WEEKLY,
                'monthly_day' => ClubEventSeries::FREQUENCY_MONTHLY_DAY,
                default => ClubEventSeries::FREQUENCY_MONTHLY_WEEKDAY,
            },
            'interval' => max(1, (int) $this->repeatInterval),
            'weekdays' => $this->repeat === 'weekly'
                ? collect($this->repeatWeekdays)->map(fn ($weekday) => (int) $weekday)->unique()->sort()->values()->all()
                : null,
            'month_week' => match ($this->repeat) {
                'monthly_last_weekday' => -1,
                'monthly_weekday' => $monthWeek > 4 ? -1 : $monthWeek,
                default => null,
            },
            'until' => $this->repeatEnd === 'until' ? ($this->repeatUntil ?: null) : null,
            'count' => $this->repeatEnd === 'count' ? (int) $this->repeatCount : null,
            'excluded_dates' => [],
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function saveSeries(ClubEventSeriesService $seriesService, array $attributes): void
    {
        /** @var Carbon $startsAt */
        $startsAt = $attributes['starts_at'];
        $rule = $this->repeatRule($startsAt);
        $dateCount = count($seriesService->occurrenceDates($startsAt, $rule));

        if ($dateCount === 0) {
            $this->addError('repeat', 'Nach dieser Regel gibt es keinen Termin – bitte Datum und Wochentage prüfen.');

            return;
        }

        if ($dateCount > ClubEventSeriesService::MAX_OCCURRENCES) {
            $this->addError(
                $this->repeatEnd === 'count' ? 'repeatCount' : 'repeatUntil',
                'Eine Serie darf höchstens '.ClubEventSeriesService::MAX_OCCURRENCES.' Termine umfassen.'
            );

            return;
        }

        $memberIDs = array_map('intval', $this->selectedMembers);

        if (! $this->editingEventID) {
            $result = $seriesService->create($attributes, $rule, $memberIDs);
            $first = $result['series']->events()->orderBy('starts_at')->first();

            ActivityLogger::log(
                'event.series_created',
                'Serientermin '.$first->activityName().' wurde mit '.$result['created'].' Terminen angelegt.',
                'ClubEvent',
                $first->eventID,
                ActivityLogger::created($this->seriesSnapshot($first), ClubEvent::ACTIVITY_LABELS)
            );

            session()->flash('success', 'Serie mit '.$result['created'].' Terminen wurde angelegt.');
            $this->resetForm();

            return;
        }

        $event = ClubEvent::query()
            ->whereNull('sourceID')
            ->whereNotNull('seriesID')
            ->with('series')
            ->findOrFail($this->editingEventID);

        $previousStart = $event->series->events()
            ->where('starts_at', '<', $event->starts_at->copy()->startOfDay())
            ->max('starts_at');

        if ($previousStart && $startsAt->copy()->startOfDay()->lte(Carbon::parse($previousStart)->startOfDay())) {
            $this->addError('startDate', 'Das Datum muss nach dem vorherigen Termin der Serie ('.Carbon::parse($previousStart)->format('d.m.Y').') liegen.');

            return;
        }

        $before = $this->seriesSnapshot($event);
        $result = $seriesService->updateFollowing($event, $attributes, $rule, $memberIDs);
        $first = $result['series']->events()->orderBy('starts_at')->first();

        $details = ActivityLogger::changes($before, $this->seriesSnapshot($first), ClubEvent::ACTIVITY_LABELS);

        ActivityLogger::log(
            'event.series_updated',
            'Serientermin '.$first->activityName().' und alle folgenden wurden geändert ('
                .$result['updated'].' geändert, '.$result['created'].' neu, '.$result['deleted'].' entfernt).',
            'ClubEvent',
            $first->eventID,
            $details
        );

        session()->flash('success', 'Serie wurde ab diesem Termin aktualisiert.');
        $this->resetForm();
    }

    /**
     * @return array<string, mixed>
     */
    private function seriesSnapshot(ClubEvent $event): array
    {
        $series = $event->series()->first();

        return $event->activitySnapshot() + [
            'series' => $series ? ClubEventSeries::describeRule($series->rule(), $event->starts_at) : null,
        ];
    }

    public function deleteFollowingEvents(int $eventID, ClubEventSeriesService $seriesService): void
    {
        $event = ClubEvent::query()
            ->whereNull('sourceID')
            ->whereNotNull('seriesID')
            ->findOrFail($eventID);

        $seriesID = $event->seriesID;
        $deleted = $seriesService->deleteFollowing($event);

        ActivityLogger::log(
            'event.series_deleted',
            'Serientermin '.$event->activityName().' und alle folgenden wurden gelöscht ('.$deleted.' Termine).',
            'ClubEvent',
            $event->eventID
        );

        if ($this->editingSeriesID === $seriesID) {
            $this->resetForm();
        }

        $this->viewingResponsesEventID = null;

        session()->flash('success', $deleted.' Termine der Serie wurden gelöscht.');
    }

    public function cancelEdit(): void
    {
        $this->resetForm();
        $this->resetValidation();
    }

    public function deleteEvent(int $eventID, ClubEventSeriesService $seriesService): void
    {
        $event = ClubEvent::query()->whereNull('sourceID')->findOrFail($eventID);

        if ($event->isPartOfSeries()) {
            $seriesService->deleteOccurrence($event);
        } else {
            $event->delete();
        }

        ActivityLogger::log(
            'event.deleted',
            'Termin '.$event->activityName().' wurde gelöscht.',
            'ClubEvent',
            $event->eventID
        );

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
            'sourceShowOnHomepage' => ['boolean'],
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
            'show_on_homepage' => $this->sourceShowOnHomepage,
        ];

        if ($this->editingSourceID) {
            $source = ClubEventSource::findOrFail($this->editingSourceID);
            $before = $source->activitySnapshot();
            $source->update($attributes);

            // Zu-/Absage gilt sofort auch für bereits übernommene Termine.
            $source->events()->update(['rsvp_enabled' => $source->rsvp_enabled]);

            $changes = ActivityLogger::changes(
                $before,
                $source->fresh()->activitySnapshot(),
                ClubEventSource::ACTIVITY_LABELS,
                ClubEventSource::ACTIVITY_MASKED
            );

            if ($changes !== []) {
                ActivityLogger::log(
                    'event.source_updated',
                    'Externer Kalender „'.$source->name.'“ wurde geändert.',
                    'ClubEventSource',
                    $source->sourceID,
                    $changes
                );
            }
        } else {
            $source = ClubEventSource::create($attributes);

            ActivityLogger::log(
                'event.source_created',
                'Externer Kalender „'.$source->name.'“ wurde hinzugefügt.',
                'ClubEventSource',
                $source->sourceID,
                ActivityLogger::created(
                    $source->activitySnapshot(),
                    ClubEventSource::ACTIVITY_LABELS,
                    ClubEventSource::ACTIVITY_MASKED
                )
            );
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
        $this->sourceShowOnHomepage = $source->show_on_homepage;

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

        ActivityLogger::log(
            'event.source_synced',
            $source->last_sync_error
                ? 'Externer Kalender „'.$source->name.'“ konnte nicht synchronisiert werden: '.$source->last_sync_error
                : 'Externer Kalender „'.$source->name.'“ wurde manuell synchronisiert ('.$count.' Termine).',
            'ClubEventSource',
            $source->sourceID
        );

        if ($source->last_sync_error) {
            session()->flash('error', $source->name.': '.$source->last_sync_error);
        } else {
            session()->flash('success', $source->name.': '.$count.' Termine synchronisiert.');
        }
    }

    public function deleteSource(int $sourceID): void
    {
        $source = ClubEventSource::findOrFail($sourceID);
        $eventCount = $source->events()->count();
        $source->delete();

        ActivityLogger::log(
            'event.source_deleted',
            'Externer Kalender „'.$source->name.'“ wurde mit '.$eventCount.' übernommenen Terminen entfernt.',
            'ClubEventSource',
            $source->sourceID
        );

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
     * Neuanlage mit allen Feldern, Änderung nur mit den geänderten Feldern
     * (kein Eintrag, wenn sich nichts geändert hat).
     *
     * @param  array<string, mixed>|null  $before
     */
    private function logEventSaved(ClubEvent $event, ?array $before): void
    {
        $after = $event->activitySnapshot();

        if ($before === null) {
            ActivityLogger::log(
                'event.created',
                'Termin '.$event->activityName().' wurde angelegt.',
                'ClubEvent',
                $event->eventID,
                ActivityLogger::created($after, ClubEvent::ACTIVITY_LABELS)
            );

            return;
        }

        $changes = ActivityLogger::changes($before, $after, ClubEvent::ACTIVITY_LABELS);

        if ($changes === []) {
            return;
        }

        ActivityLogger::log(
            'event.updated',
            'Termin '.$event->activityName().' wurde geändert.',
            'ClubEvent',
            $event->eventID,
            $changes
        );
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
            'showOnHomepage',
            'selectedMembers',
            'editingEventID',
            'repeat',
            'repeatInterval',
            'repeatWeekdays',
            'repeatEnd',
            'repeatUntil',
            'repeatCount',
            'editScope',
            'editingSeriesID',
        ]);
    }

    private function resetSourceForm(): void
    {
        $this->reset([
            'sourceName',
            'sourceUrl',
            'sourceRsvpEnabled',
            'sourceActive',
            'sourceShowOnHomepage',
            'editingSourceID',
        ]);
    }

    /**
     * Auswahl „Wiederholung“ mit Beschriftungen passend zum gewählten Datum,
     * z. B. „Monatlich am 2. Dienstag“.
     *
     * @return array<string, string>
     */
    private function repeatOptions(): array
    {
        $start = $this->validStartDate();
        $options = $this->editingEventID ? [] : ['none' => 'Keine'];
        $options['weekly'] = 'Wöchentlich';

        if (! $start) {
            return $options + [
                'monthly_day' => 'Monatlich am gleichen Tag',
                'monthly_weekday' => 'Monatlich am gleichen Wochentag (z. B. 2. Dienstag)',
            ];
        }

        $weekday = ClubEventSeries::WEEKDAY_NAMES[$start->dayOfWeekIso];
        $monthWeek = intdiv($start->day - 1, 7) + 1;

        $options['monthly_day'] = 'Monatlich am '.$start->day.'.';

        if ($monthWeek <= 4) {
            $options['monthly_weekday'] = 'Monatlich am '.$monthWeek.'. '.$weekday;
        }

        if ($start->day + 7 > $start->daysInMonth) {
            $options['monthly_last_weekday'] = 'Monatlich am letzten '.$weekday;
        }

        return $options;
    }

    /**
     * Vorschau der Serie (Anzahl, erster und letzter Termin), solange die
     * Eingaben vollständig sind.
     *
     * @return array{count: int, first: Carbon, last: Carbon, description: string, tooMany: bool}|null
     */
    private function seriesPreview(): ?array
    {
        $start = $this->validStartDate();
        $showsRule = $this->editingEventID ? $this->savesSeries() : $this->repeat !== 'none';

        if (! $showsRule || ! $start || ! array_key_exists($this->repeat, $this->repeatOptions())) {
            return null;
        }

        if ($this->repeatEnd === 'until' && ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->repeatUntil)) {
            return null;
        }

        if ($this->repeatEnd === 'count' && ! ctype_digit($this->repeatCount)) {
            return null;
        }

        $rule = $this->repeatRule($start);
        $dates = app(ClubEventSeriesService::class)->occurrenceDates($start, $rule);

        if ($dates === []) {
            return null;
        }

        return [
            'count' => count($dates),
            'first' => Carbon::instance(reset($dates)),
            'last' => Carbon::instance(end($dates)),
            'description' => ClubEventSeries::describeRule($rule, $start),
            'tooMany' => count($dates) > ClubEventSeriesService::MAX_OCCURRENCES,
        ];
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
            ->with([
                'source',
                'responses.member',
                'members',
                'series' => fn ($query) => $query->withMin('events', 'starts_at'),
            ])
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
            'repeatOptions' => $this->repeatOptions(),
            'seriesPreview' => $this->seriesPreview(),
            'editingSeriesDescription' => $this->editingSeriesID
                ? ClubEventSeries::query()->withMin('events', 'starts_at')->find($this->editingSeriesID)?->describe()
                : null,
        ])->layout('layouts.app', [
            'title' => 'Termine | VEMA',
            'heading' => 'Termine',
        ]);
    }
}
