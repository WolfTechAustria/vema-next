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

    @if($swapsAvailable && ($incomingSwaps->isNotEmpty() || $outgoingSwaps->isNotEmpty()))
        <!-- Tauschanfragen -->
        <section class="rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-6 py-4">
                <h3 class="font-semibold text-slate-900">
                    Tauschanfragen
                </h3>
            </div>

            @error('swap')
                <p class="border-b border-red-100 bg-red-50 px-6 py-3 text-sm text-red-700">{{ $message }}</p>
            @enderror

            @foreach($incomingSwaps as $swap)
                @php($swapWarnings = $swap->status === \App\Enums\DutySwapStatus::Pending ? $swaps->warningsForRequest($swap, $member) : [])
                <div wire:key="swap-in-{{ $swap->swapID }}" class="border-b border-slate-100 px-6 py-4 last:border-b-0">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="text-sm text-slate-700">
                            @if($swap->isSwap())
                                <span class="font-medium text-slate-900">{{ $swap->requester->full_name }}</span> möchte tauschen:
                                du übernimmst <strong>{{ $swaps->describe($swap->assignment->event) }}</strong>,
                                dafür übernimmt {{ $swap->requester->name }} deinen Dienst <strong>{{ $swaps->describe($swap->targetAssignment?->event) }}</strong>.
                            @else
                                <span class="font-medium text-slate-900">{{ $swap->requester->full_name }}</span> sucht
                                {{ $swap->isOpenToAll() ? 'jemanden' : 'dich' }} für <strong>{{ $swaps->describe($swap->assignment->event) }}</strong>.
                                @if($swap->isOpenToAll())
                                    <span class="text-slate-500">(an alle im Dienstplan – wer zuerst annimmt, übernimmt)</span>
                                @endif
                            @endif

                            @if($swap->message)
                                <p class="mt-1 italic text-slate-500">„{{ $swap->message }}“</p>
                            @endif
                        </div>

                        @if($swap->status === \App\Enums\DutySwapStatus::AwaitingApproval)
                            <span class="rounded-full px-2.5 py-0.5 text-xs font-medium {{ $swap->status->badgeClasses() }}">
                                Angenommen – {{ $swap->status->label() }}
                            </span>
                        @else
                            <div class="flex items-center gap-2">
                                <button
                                    type="button"
                                    wire:click="acceptSwap({{ $swap->swapID }})"
                                    wire:confirm="{{ $swap->isSwap() ? 'Dienste tauschen?' : 'Dienst übernehmen?' }}"
                                    class="rounded-md bg-emerald-600 px-3 py-1.5 text-sm font-semibold text-white hover:bg-emerald-700"
                                >
                                    {{ $swap->isSwap() ? 'Tauschen' : 'Übernehmen' }}
                                </button>
                                @unless($swap->isOpenToAll())
                                    <button
                                        type="button"
                                        wire:click="declineSwap({{ $swap->swapID }})"
                                        class="rounded-md border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50"
                                    >
                                        Ablehnen
                                    </button>
                                @endunless
                            </div>
                        @endif
                    </div>

                    @if($swapWarnings !== [])
                        <div class="mt-2 rounded-md bg-amber-50 px-3 py-2 text-xs text-amber-800">
                            @foreach($swapWarnings as $warning)
                                <div>{{ $warning }}</div>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endforeach

            @foreach($outgoingSwaps as $swap)
                <div wire:key="swap-out-{{ $swap->swapID }}" class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-6 py-4 last:border-b-0">
                    <div class="text-sm text-slate-700">
                        Deine Anfrage:
                        {{ $swap->isSwap() ? 'Tausch' : 'Abgabe' }} von <strong>{{ $swaps->describe($swap->assignment->event) }}</strong>
                        @if($swap->isSwap())
                            gegen <strong>{{ $swaps->describe($swap->targetAssignment?->event) }}</strong>
                        @endif
                        – {{ $swap->targetMember ? 'an '.$swap->targetMember->full_name : 'an alle im Dienstplan' }}
                        @if($swap->acceptedBy)
                            <span class="text-slate-500">(angenommen von {{ $swap->acceptedBy->full_name }})</span>
                        @endif
                    </div>

                    <div class="flex items-center gap-3">
                        <span class="rounded-full px-2.5 py-0.5 text-xs font-medium {{ $swap->status->badgeClasses() }}">
                            {{ $swap->status->label() }}
                        </span>
                        @if($swap->status->isOpen())
                            <button
                                type="button"
                                wire:click="cancelSwap({{ $swap->swapID }})"
                                wire:confirm="Anfrage zurückziehen?"
                                class="text-sm text-red-600 hover:text-red-800"
                            >
                                Zurückziehen
                            </button>
                        @endif
                    </div>
                </div>
            @endforeach
        </section>
    @endif

    <!-- Anstehende Dienste -->
    <section class="rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-6 py-4">
            <h3 class="font-semibold text-slate-900">
                Anstehende Dienste
            </h3>
        </div>

        @forelse ($upcoming as $assignment)
            <div wire:key="upcoming-{{ $assignment->assignmentID }}" class="border-b border-slate-100 px-6 py-4 last:border-b-0">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <div class="font-medium text-slate-900">
                            {{ $assignment->event->duty_name }}
                        </div>
                        <div class="text-sm text-slate-500">
                            {{ $assignment->event->duty_date->translatedFormat('l, d.m.Y') }}
                        </div>
                    </div>

                    @if($swapsAvailable)
                        @if(in_array($assignment->assignmentID, $openSwapAssignmentIDs, true))
                            <span class="rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-medium text-amber-800">Tausch angefragt</span>
                        @elseif($swapForm === null && $swaps->isBeforeDeadline($assignment->event))
                            <button
                                type="button"
                                wire:click="openSwap({{ $assignment->assignmentID }})"
                                class="rounded-md border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50"
                            >
                                Tauschen / abgeben
                            </button>
                        @endif
                    @endif
                </div>

                @if($swapForm && $swapForm['assignment']->assignmentID === $assignment->assignmentID)
                    <form wire:submit="sendSwapRequest" class="mt-4 space-y-4 rounded-lg border border-slate-200 bg-slate-50 p-4">
                        <div class="flex flex-wrap gap-4 text-sm">
                            <label class="flex items-center gap-2">
                                <input type="radio" wire:model.live="swapType" value="handover">
                                Abgeben (jemand übernimmt meinen Dienst)
                            </label>
                            <label class="flex items-center gap-2">
                                <input type="radio" wire:model.live="swapType" value="swap">
                                Tauschen (gegen einen Dienst eines anderen Mitglieds)
                            </label>
                        </div>

                        @if($swapForm['candidates']->isEmpty())
                            <p class="text-sm text-slate-500">Im Dienstplan gibt es keine weiteren Helfer, an die du eine Anfrage senden kannst.</p>
                        @else
                            <div class="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <label for="swap-target" class="mb-1 block text-sm font-medium text-slate-700">An</label>
                                    <select id="swap-target" wire:model.live="swapTarget" class="w-full rounded-md border-slate-300 text-sm">
                                        @if($swapType === 'handover')
                                            <option value="all">Alle im Dienstplan</option>
                                        @else
                                            <option value="">Mitglied wählen …</option>
                                        @endif
                                        @foreach($swapForm['candidates'] as $candidate)
                                            <option value="{{ $candidate->memberID }}">{{ $candidate->full_name }}</option>
                                        @endforeach
                                    </select>
                                    @error('swapTarget') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                                </div>

                                @if($swapType === 'swap' && $swapTarget !== '')
                                    <div>
                                        <label for="swap-target-assignment" class="mb-1 block text-sm font-medium text-slate-700">Gegen diesen Dienst</label>
                                        @if($swapForm['targetAssignments']->isEmpty())
                                            <p class="text-sm text-slate-500">Dieses Mitglied hat keine tauschbaren Dienste in diesem Dienstplan.</p>
                                        @else
                                            <select id="swap-target-assignment" wire:model.live="swapTargetAssignmentID" class="w-full rounded-md border-slate-300 text-sm">
                                                <option value="">Dienst wählen …</option>
                                                @foreach($swapForm['targetAssignments'] as $targetAssignment)
                                                    <option value="{{ $targetAssignment->assignmentID }}">{{ $swaps->describe($targetAssignment->event) }}</option>
                                                @endforeach
                                            </select>
                                        @endif
                                        @error('swapTargetAssignmentID') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                                    </div>
                                @endif
                            </div>

                            @if($swapForm['warnings'] !== [])
                                <div class="rounded-md bg-amber-50 px-3 py-2 text-xs text-amber-800">
                                    @foreach($swapForm['warnings'] as $warning)
                                        <div>{{ $warning }}</div>
                                    @endforeach
                                </div>
                            @endif

                            <div>
                                <label for="swap-message" class="mb-1 block text-sm font-medium text-slate-700">Nachricht (optional)</label>
                                <textarea id="swap-message" wire:model="swapMessage" rows="2" maxlength="500" class="w-full rounded-md border-slate-300 text-sm"></textarea>
                                @error('swapMessage') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>
                        @endif

                        @error('swap') <p class="text-sm text-red-600">{{ $message }}</p> @enderror

                        <div class="flex gap-2">
                            @if($swapForm['candidates']->isNotEmpty())
                                <button type="submit" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">
                                    Anfrage senden
                                </button>
                            @endif
                            <button type="button" wire:click="closeSwap" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                                Abbrechen
                            </button>
                        </div>
                    </form>
                @endif
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
