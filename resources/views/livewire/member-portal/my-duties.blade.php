<div class="space-y-6">

    @include('partials.flash-messages')

    <!-- Kalender-Abo -->
    <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <h3 class="font-semibold text-slate-900">
            Kalender-Abo
        </h3>

        <p class="mt-1 text-sm text-slate-500">
            Dieser Link kann in Google Kalender, Apple Kalender oder Outlook
            als Kalender-Abo hinzugefügt werden. Er aktualisiert sich
            automatisch, sobald sich deine Dienste ändern.
            @feature('events')
                Er enthält auch die Vereinstermine – es ist derselbe Link wie unter „Termine“.
            @endfeature
        </p>

        <div class="mt-4 flex flex-col gap-2 sm:flex-row sm:items-center">
            <input
                type="text"
                readonly
                value="{{ $icalUrl }}"
                onclick="this.select()"
                class="flex-1 rounded-md border-slate-300 bg-slate-50 text-sm"
            >
            </input>
            <a
                href="{{ str_replace(['https://', 'http://'], 'webcal://', $icalUrl) }}"
                class="inline-flex items-center gap-2 rounded-md bg-blue-600 px-3 py-2 text-sm font-medium text-white hover:bg-blue-700"
            >
                Zum Kalender hinzufügen
            </a>

            <button
                type="button"
                wire:click="regenerateIcalLink"
                wire:confirm="Alten Link ungültig machen und einen neuen erzeugen?"
                class="whitespace-nowrap rounded-md border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
            >
                Link erneuern
            </button>
        </div>
    </section>

    @if($sharingAvailable)
        <!-- Mit mir geteilte Dienste -->
        @if($sharesReceived->isNotEmpty())
            <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                <h3 class="font-semibold text-slate-900">
                    Mit mir geteilte Dienste
                </h3>
                <p class="mt-1 text-sm text-slate-500">
                    Diese Mitglieder haben dir ihre Dienste freigegeben. Zeige sie in deinem Kalender-Abo an
                    (als „Dienst von …“ gekennzeichnet) oder abonniere sie über einen eigenen Link.
                </p>

                <div class="mt-3 divide-y divide-slate-100">
                    @foreach($sharesReceived as $share)
                        <div wire:key="duty-share-received-{{ $share->shareID }}" class="py-3">
                            <div class="flex flex-wrap items-center justify-between gap-3">
                                <span class="text-sm font-medium text-slate-800">
                                    {{ $share->owner->full_name }}
                                </span>

                                <div class="flex flex-wrap items-center gap-3">
                                    <div class="flex items-center gap-2 text-sm text-slate-600">
                                        <button
                                            type="button"
                                            wire:click="toggleInOwnCalendar({{ $share->shareID }})"
                                            aria-label="Dienste von {{ $share->owner->full_name }} in meinem Kalender-Abo anzeigen"
                                            aria-pressed="{{ $share->in_own_calendar ? 'true' : 'false' }}"
                                            class="{{ $share->in_own_calendar ? 'bg-emerald-600' : 'bg-slate-300' }} relative inline-flex h-6 w-11 shrink-0 items-center rounded-full transition"
                                        >
                                            <span class="{{ $share->in_own_calendar ? 'translate-x-6' : 'translate-x-1' }} inline-block h-4 w-4 transform rounded-full bg-white transition"></span>
                                        </button>
                                        In meinem Kalender-Abo
                                    </div>

                                    <button
                                        type="button"
                                        wire:click="toggleSharedLink({{ $share->shareID }})"
                                        class="rounded-md border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50"
                                    >
                                        {{ $visibleLinkShareID === $share->shareID ? 'Link ausblenden' : 'Eigener Abo-Link' }}
                                    </button>

                                    <button
                                        type="button"
                                        wire:click="endShare({{ $share->shareID }})"
                                        wire:confirm="Freigabe von {{ $share->owner->full_name }} entfernen?"
                                        class="text-sm text-red-600 hover:text-red-800"
                                    >
                                        Entfernen
                                    </button>
                                </div>
                            </div>

                            @if($visibleLinkShareID === $share->shareID && $share->ical_token)
                                @php($sharedUrl = route('member-calendar.shared', $share->ical_token))

                                <div class="mt-3 flex flex-col gap-2 sm:flex-row sm:items-center">
                                    <input
                                        type="text"
                                        readonly
                                        value="{{ $sharedUrl }}"
                                        onclick="this.select()"
                                        class="flex-1 rounded-md border-slate-300 bg-slate-50 text-sm"
                                    >
                                    <a
                                        href="{{ str_replace(['https://', 'http://'], 'webcal://', $sharedUrl) }}"
                                        class="inline-flex items-center gap-2 rounded-md bg-blue-600 px-3 py-2 text-sm font-medium text-white hover:bg-blue-700"
                                    >
                                        Zum Kalender hinzufügen
                                    </a>
                                    <button
                                        type="button"
                                        wire:click="regenerateSharedLink({{ $share->shareID }})"
                                        wire:confirm="Alten Link ungültig machen und einen neuen erzeugen?"
                                        class="whitespace-nowrap rounded-md border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
                                    >
                                        Link erneuern
                                    </button>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </section>
        @endif

        <!-- Eigene Dienste freigeben -->
        <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <h3 class="font-semibold text-slate-900">
                Meine Dienste freigeben
            </h3>
            <p class="mt-1 text-sm text-slate-500">
                Gib deine Dienste z. B. deiner Familie frei. Die Person sieht Datum, Uhrzeit und Bezeichnung
                deiner Dienste in ihrem Kalender-Abo – sonst nichts.
            </p>

            @if($sharesGiven->isNotEmpty())
                <div class="mt-3 divide-y divide-slate-100">
                    @foreach($sharesGiven as $share)
                        <div wire:key="duty-share-given-{{ $share->shareID }}" class="flex items-center justify-between gap-3 py-2.5">
                            <span class="text-sm text-slate-700">{{ $share->viewer->full_name }}</span>
                            <button
                                type="button"
                                wire:click="endShare({{ $share->shareID }})"
                                wire:confirm="Freigabe für {{ $share->viewer->full_name }} beenden?"
                                class="text-sm text-red-600 hover:text-red-800"
                            >
                                Freigabe beenden
                            </button>
                        </div>
                    @endforeach
                </div>
            @endif

            <div class="mt-4">
                <label for="share-search" class="mb-1 block text-sm font-medium text-slate-700">Mitglied hinzufügen</label>
                <input
                    id="share-search"
                    type="text"
                    wire:model.live.debounce.300ms="shareSearch"
                    placeholder="Name oder E-Mail-Adresse"
                    autocomplete="off"
                    class="w-full rounded-md border-slate-300 text-sm sm:max-w-sm"
                >
                @error('shareSearch') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror

                @if(mb_strlen(trim($shareSearch)) >= \App\Services\DutyCalendarShares::MIN_SEARCH_LENGTH)
                    <div class="mt-2 divide-y divide-slate-100 rounded-lg border border-slate-200 sm:max-w-sm">
                        @forelse($shareCandidates as $candidate)
                            <div wire:key="share-candidate-{{ $candidate->memberID }}" class="flex items-center justify-between gap-3 px-3 py-2">
                                <span class="text-sm text-slate-700">{{ $candidate->full_name }}</span>
                                <button
                                    type="button"
                                    wire:click="shareWith({{ $candidate->memberID }})"
                                    class="rounded-md bg-slate-900 px-3 py-1 text-xs font-semibold text-white hover:bg-slate-800"
                                >
                                    Freigeben
                                </button>
                            </div>
                        @empty
                            <p class="px-3 py-2 text-sm text-slate-500">
                                {{ str_contains($shareSearch, '@') ? 'Kein aktives Mitglied mit dieser E-Mail-Adresse gefunden.' : 'Kein passendes Mitglied gefunden.' }}
                            </p>
                        @endforelse
                    </div>
                @endif
            </div>
        </section>
    @endif

    <!-- Reminder -->
    <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="flex items-center justify-between">
            <div>
                <h3 class="font-semibold text-slate-900">
                    E-Mail-Erinnerung
                </h3>
                <p class="mt-1 text-sm text-slate-500">
                    Erhalte 2 Tage vor einem Dienst automatisch eine E-Mail.
                </p>
            </div>

            <button
                type="button"
                wire:click="toggleReminder"
                class="{{ $reminderEnabled ? 'bg-emerald-600' : 'bg-slate-300' }} relative inline-flex h-6 w-11 items-center rounded-full transition"
            >
                <span class="{{ $reminderEnabled ? 'translate-x-6' : 'translate-x-1' }} inline-block h-4 w-4 transform rounded-full bg-white transition"></span>
            </button>
        </div>
    </section>

    <!-- Anstehende Dienste -->
    <section class="rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-6 py-4">
            <h3 class="font-semibold text-slate-900">
                Anstehende Dienste
            </h3>
        </div>

        @forelse ($upcoming as $assignment)
            <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4 last:border-b-0">
                <div>
                    <div class="font-medium text-slate-900">
                        {{ $assignment->event->duty_name }}
                    </div>
                    <div class="text-sm text-slate-500">
                        {{ $assignment->event->duty_date->translatedFormat('l, d.m.Y') }}
                    </div>
                </div>
            </div>
        @empty
            <p class="px-6 py-4 text-sm text-slate-500">
                Aktuell keine anstehenden Dienste.
            </p>
        @endforelse
    </section>

    <!-- Vergangene Dienste -->
    <section class="rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-6 py-4">
            <h3 class="font-semibold text-slate-900">
                Vergangene Dienste
            </h3>
        </div>

        @forelse ($past as $assignment)
            <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4 last:border-b-0">
                <div>
                    <div class="font-medium text-slate-700">
                        {{ $assignment->event->duty_name }}
                    </div>
                    <div class="text-sm text-slate-500">
                        {{ $assignment->event->duty_date->translatedFormat('l, d.m.Y') }}
                    </div>
                </div>
            </div>
        @empty
            <p class="px-6 py-4 text-sm text-slate-500">
                Keine vergangenen Dienste.
            </p>
        @endforelse
    </section>

</div>
