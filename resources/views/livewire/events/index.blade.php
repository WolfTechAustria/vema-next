<div class="space-y-6">

    <div>
        <h2 class="text-2xl font-bold tracking-tight">
            Termine
        </h2>

        <p class="mt-1 text-sm text-slate-500">
            Vereinstermine planen und Zu- und Absagen der Mitglieder einsehen.
        </p>
    </div>

    @include('partials.flash-messages')

    <div class="grid gap-6 lg:grid-cols-2">

        <section class="h-fit rounded-xl border border-slate-200 bg-white p-6 shadow-sm">

            <div class="flex items-center justify-between">

                <h3 class="text-lg font-semibold">
                    {{ $editingEventID ? 'Termin bearbeiten' : 'Neuer Termin' }}
                </h3>

                @if($editingEventID)
                    <button
                        type="button"
                        wire:click="cancelEdit"
                        class="text-sm font-medium text-slate-500 hover:text-slate-800"
                    >
                        Abbrechen
                    </button>
                @endif

            </div>

            <form wire:submit="saveEvent" class="mt-4 space-y-4">

                <div>
                    <label class="mb-1 block text-sm font-medium">
                        Titel
                    </label>

                    <input
                        type="text"
                        wire:model="title"
                        class="w-full rounded-lg border border-slate-300 px-3 py-2"
                    >

                    @error('title')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <label class="flex items-center gap-2 text-sm">
                    <input
                        type="checkbox"
                        wire:model.live="allDay"
                        class="h-4 w-4 rounded border-slate-300"
                    >
                    Ganztägig
                </label>

                <div class="grid gap-4 sm:grid-cols-2">

                    <div>
                        <label class="mb-1 block text-sm font-medium">
                            Datum
                        </label>

                        <input
                            type="date"
                            wire:model="startDate"
                            class="w-full rounded-lg border border-slate-300 px-3 py-2"
                        >

                        @error('startDate')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    @unless($allDay)
                        <div>
                            <label class="mb-1 block text-sm font-medium">
                                Beginn
                            </label>

                            <input
                                type="time"
                                wire:model="startTime"
                                class="w-full rounded-lg border border-slate-300 px-3 py-2"
                            >

                            @error('startTime')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    @endunless

                    <div>
                        <label class="mb-1 block text-sm font-medium">
                            Enddatum <span class="font-normal text-slate-400">(optional)</span>
                        </label>

                        <input
                            type="date"
                            wire:model="endDate"
                            class="w-full rounded-lg border border-slate-300 px-3 py-2"
                        >

                        @error('endDate')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    @unless($allDay)
                        <div>
                            <label class="mb-1 block text-sm font-medium">
                                Ende <span class="font-normal text-slate-400">(optional)</span>
                            </label>

                            <input
                                type="time"
                                wire:model="endTime"
                                class="w-full rounded-lg border border-slate-300 px-3 py-2"
                            >

                            @error('endTime')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    @endunless

                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium">
                        Ort
                    </label>

                    <input
                        type="text"
                        wire:model="location"
                        class="w-full rounded-lg border border-slate-300 px-3 py-2"
                    >

                    @error('location')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium">
                        Beschreibung
                    </label>

                    <textarea
                        wire:model="description"
                        rows="3"
                        class="w-full rounded-lg border border-slate-300 px-3 py-2"
                    ></textarea>

                    @error('description')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <label class="flex items-center gap-2 text-sm">
                    <input
                        type="checkbox"
                        wire:model="rsvpEnabled"
                        class="h-4 w-4 rounded border-slate-300"
                    >
                    Mitglieder können im Mitgliederportal zu- oder absagen
                </label>

                <button
                    type="submit"
                    wire:loading.attr="disabled"
                    wire:target="saveEvent"
                    class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white disabled:opacity-60"
                >
                    <span wire:loading.remove wire:target="saveEvent">
                        {{ $editingEventID ? 'Änderungen speichern' : 'Termin anlegen' }}
                    </span>

                    <span wire:loading wire:target="saveEvent">
                        Speichern …
                    </span>
                </button>

            </form>

        </section>

        <section class="h-fit rounded-xl border border-slate-200 bg-white shadow-sm">

            <div class="flex items-center justify-between gap-4 border-b border-slate-200 px-6 py-4">
                <h3 class="font-semibold">
                    {{ $showPast ? 'Vergangene Termine' : 'Anstehende Termine' }}
                </h3>

                <button
                    type="button"
                    wire:click="$toggle('showPast')"
                    class="text-sm font-medium text-slate-500 hover:text-slate-800"
                >
                    {{ $showPast ? 'Anstehende anzeigen' : 'Vergangene anzeigen' }}
                </button>
            </div>

            <div class="divide-y divide-slate-100">

                @forelse($events as $event)

                    <div wire:key="club-event-{{ $event->eventID }}" class="px-6 py-4">

                        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">

                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2 font-medium text-slate-900">
                                    {{ $event->title }}

                                    @if($event->source)
                                        <span class="rounded-full bg-sky-50 px-2 py-0.5 text-xs font-medium text-sky-700">
                                            {{ $event->source->name }}
                                        </span>
                                    @endif
                                </div>

                                <div class="mt-1 text-sm text-slate-500">
                                    {{ $event->formattedPeriod() }}
                                    @if($event->location)
                                        · {{ $event->location }}
                                    @endif
                                </div>

                                @if($event->description)
                                    <div class="mt-1 whitespace-pre-line text-sm text-slate-500">{{ $event->description }}</div>
                                @endif

                                @if($event->rsvp_enabled)
                                    <div class="mt-2 flex flex-wrap gap-3 text-xs">
                                        <span class="text-emerald-700">
                                            {{ $event->countResponses(\App\Enums\EventResponseStatus::Attending) }} Zusagen
                                        </span>
                                        <span class="text-red-700">
                                            {{ $event->countResponses(\App\Enums\EventResponseStatus::Declined) }} Absagen
                                        </span>
                                    </div>
                                @else
                                    <div class="mt-2 text-xs text-slate-400">
                                        Ohne Zu-/Absage
                                    </div>
                                @endif
                            </div>

                            <div class="flex shrink-0 items-center gap-3">

                                @if($event->rsvp_enabled)
                                    <button
                                        type="button"
                                        wire:click="toggleResponses({{ $event->eventID }})"
                                        class="text-sm font-medium text-slate-600 hover:text-slate-900"
                                    >
                                        Rückmeldungen
                                    </button>
                                @endif

                                @unless($event->isExternal())
                                    <button
                                        type="button"
                                        wire:click="editEvent({{ $event->eventID }})"
                                        class="text-sm font-medium text-slate-600 hover:text-slate-900"
                                    >
                                        Bearbeiten
                                    </button>

                                    <button
                                        type="button"
                                        wire:click="deleteEvent({{ $event->eventID }})"
                                        wire:confirm="Termin samt allen Rückmeldungen wirklich löschen?"
                                        class="text-sm font-medium text-red-600 hover:text-red-800"
                                    >
                                        Löschen
                                    </button>
                                @endunless

                            </div>

                        </div>

                        @if($responseOverview && $responseOverview['event']->is($event))
                            <div class="mt-4 grid gap-4 rounded-lg bg-slate-50 p-4 text-sm sm:grid-cols-2">

                                @foreach([
                                    'Zugesagt' => $responseOverview['attending']->map(fn ($response) => $response->member?->full_name),
                                    'Abgesagt' => $responseOverview['declined']->map(fn ($response) => $response->member?->full_name),                                ] as $label => $names)
                                    <div>
                                        <div class="font-medium text-slate-900">
                                            {{ $label }} ({{ $names->filter()->count() }})
                                        </div>

                                        <ul class="mt-1 space-y-0.5 text-slate-600">
                                            @forelse($names->filter() as $name)
                                                <li>{{ $name }}</li>
                                            @empty
                                                <li class="text-slate-400">–</li>
                                            @endforelse
                                        </ul>
                                    </div>
                                @endforeach

                            </div>
                        @endif

                    </div>

                @empty

                    <div class="px-6 py-8 text-center text-sm text-slate-500">
                        {{ $showPast ? 'Keine vergangenen Termine.' : 'Keine anstehenden Termine.' }}
                    </div>

                @endforelse

            </div>

            @if($hiddenEventsCount > 0)
                <div class="border-t border-slate-200 px-6 py-3 text-center">
                    <button
                        type="button"
                        wire:click="showMoreEvents"
                        class="text-sm font-medium text-slate-600 hover:text-slate-900"
                    >
                        Mehr anzeigen ({{ $hiddenEventsCount }} weitere)
                    </button>
                </div>
            @endif

        </section>

    </div>

    <section class="rounded-xl border border-slate-200 bg-white shadow-sm">

        <div class="border-b border-slate-200 px-6 py-4">
            <h3 class="font-semibold">
                Externe Kalender
            </h3>

            <p class="mt-1 text-sm text-slate-500">
                Termine aus iCal-Links (z. B. Verband, Gemeinde, Google Kalender) werden stündlich übernommen.
                Änderungen erfolgen im Ursprungskalender – Zu-/Absagen bleiben bei uns und werden nicht zurückgemeldet.
            </p>
        </div>

        <div class="grid gap-6 p-6 lg:grid-cols-2">

            <form wire:submit="saveSource" class="space-y-4">

                <div class="flex items-center justify-between">
                    <h4 class="text-sm font-semibold">
                        {{ $editingSourceID ? 'Kalender bearbeiten' : 'Kalender hinzufügen' }}
                    </h4>

                    @if($editingSourceID)
                        <button
                            type="button"
                            wire:click="cancelSourceEdit"
                            class="text-sm font-medium text-slate-500 hover:text-slate-800"
                        >
                            Abbrechen
                        </button>
                    @endif
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium">
                        Beschriftung
                    </label>

                    <input
                        type="text"
                        wire:model="sourceName"
                        placeholder="z. B. Termine Landesverband"
                        class="w-full rounded-lg border border-slate-300 px-3 py-2"
                    >

                    <p class="mt-1 text-xs text-slate-500">
                        Unter diesem Namen sehen die Mitglieder die Termine im Portal.
                    </p>

                    @error('sourceName')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium">
                        iCal-Link
                    </label>

                    <input
                        type="url"
                        wire:model="sourceUrl"
                        placeholder="https://… oder webcal://…"
                        class="w-full rounded-lg border border-slate-300 px-3 py-2"
                    >

                    @error('sourceUrl')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <label class="flex items-center gap-2 text-sm">
                    <input
                        type="checkbox"
                        wire:model="sourceRsvpEnabled"
                        class="h-4 w-4 rounded border-slate-300"
                    >
                    Mitglieder können zu- oder absagen
                </label>

                <label class="flex items-center gap-2 text-sm">
                    <input
                        type="checkbox"
                        wire:model="sourceActive"
                        class="h-4 w-4 rounded border-slate-300"
                    >
                    Aktiv (synchronisieren und im Portal anzeigen)
                </label>

                <button
                    type="submit"
                    wire:loading.attr="disabled"
                    wire:target="saveSource"
                    class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white disabled:opacity-60"
                >
                    <span wire:loading.remove wire:target="saveSource">
                        {{ $editingSourceID ? 'Änderungen speichern' : 'Kalender hinzufügen' }}
                    </span>

                    <span wire:loading wire:target="saveSource">
                        Synchronisiere …
                    </span>
                </button>

            </form>

            <div class="divide-y divide-slate-100 rounded-lg border border-slate-200">

                @forelse($sources as $source)

                    <div wire:key="event-source-{{ $source->sourceID }}" class="px-4 py-3">

                        <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">

                            <div class="min-w-0">
                                <div class="font-medium text-slate-900">
                                    {{ $source->name }}

                                    @unless($source->active)
                                        <span class="ml-1 text-xs font-normal text-slate-400">(inaktiv)</span>
                                    @endunless
                                </div>

                                <div class="mt-1 text-xs text-slate-500">
                                    {{ $source->events_count }} Termine
                                    · {{ $source->rsvp_enabled ? 'mit Zu-/Absage' : 'nur Anzeige' }}
                                    · {{ $source->last_synced_at ? 'zuletzt synchronisiert '.$source->last_synced_at->diffForHumans() : 'noch nie synchronisiert' }}
                                </div>

                                @if($source->last_sync_error)
                                    <div class="mt-1 text-xs text-red-600">
                                        {{ $source->last_sync_error }}
                                    </div>
                                @endif
                            </div>

                            <div class="flex shrink-0 items-center gap-3">
                                <button
                                    type="button"
                                    wire:click="syncSource({{ $source->sourceID }})"
                                    wire:loading.attr="disabled"
                                    wire:target="syncSource({{ $source->sourceID }})"
                                    class="text-sm font-medium text-slate-600 hover:text-slate-900 disabled:opacity-60"
                                >
                                    Jetzt synchronisieren
                                </button>

                                <button
                                    type="button"
                                    wire:click="editSource({{ $source->sourceID }})"
                                    class="text-sm font-medium text-slate-600 hover:text-slate-900"
                                >
                                    Bearbeiten
                                </button>

                                <button
                                    type="button"
                                    wire:click="deleteSource({{ $source->sourceID }})"
                                    wire:confirm="Kalender entfernen? Alle übernommenen Termine samt Rückmeldungen werden gelöscht."
                                    class="text-sm font-medium text-red-600 hover:text-red-800"
                                >
                                    Löschen
                                </button>
                            </div>

                        </div>

                    </div>

                @empty

                    <div class="px-4 py-8 text-center text-sm text-slate-500">
                        Noch keine externen Kalender hinterlegt.
                    </div>

                @endforelse

            </div>

        </div>

    </section>

</div>
