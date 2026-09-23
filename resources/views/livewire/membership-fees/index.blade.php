<div class="space-y-6">

    <div>
        <h2 class="text-2xl font-bold tracking-tight">
            Mitgliedsbeiträge
        </h2>

        @include('partials.flash-messages')

        <p class="mt-1 text-sm text-slate-500">
            Historische und aktuelle Beitragsstände.
        </p>
    </div>

    <div class="flex flex-wrap items-center gap-2">
        @if($selectedYear->active)
            <span class="rounded-full bg-green-50 px-2.5 py-1 text-xs font-semibold text-green-700">
                Offen
            </span>
            @else
                <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600">
                    Abgeschlossen
                </span>
        @endif
            <button
                type="button"
                wire:click="toggleYearActive"
                wire:confirm="{{ $selectedYear->active
        ? 'Dieses Beitragsjahr wirklich abschließen? Danach sind keine Änderungen mehr möglich.'
        : 'Dieses Beitragsjahr wieder zur Bearbeitung öffnen?' }}"
                class="
        rounded-lg px-4 py-2 text-sm font-medium
        {{ $selectedYear->active
            ? 'border border-slate-300 bg-white text-slate-700 hover:bg-slate-50'
            : 'bg-slate-900 text-white hover:bg-slate-800'
        }}
    "
            >
                {{ $selectedYear->active
                    ? 'Beitragsjahr abschließen'
                    : 'Beitragsjahr wieder öffnen'
                }}
            </button>


            @if($selectedYear)

                <a
                    href="{{ route(
            'membership-fees.open-overview',
            $selectedYear->yearID
        ) }}"
                    class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
                >
                    PDF offene Beiträge
                </a>

            @endif

    </div>





    <section class="rounded-xl border border-slate-200 bg-white shadow-sm">


        <div class="grid grid-cols-2 gap-4 p-4 sm:p-6 md:grid-cols-4 md:items-end">
            <div>

                <label class="mb-1 block text-sm font-medium text-slate-700">
                    Beitragsjahr
                </label>

                <select
                    wire:model.live="yearId"
                    class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2"
                >
                    @foreach($years as $year)
                        <option value="{{ $year->yearID }}">
                            {{ $year->year }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">
                    Status
                </label>

                <select
                    wire:model.live="statusFilter"
                    class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2"
                >
                    <option value="all">Alle</option>
                    <option value="open">Offen</option>
                    <option value="paid">Bezahlt</option>
                    <option value="exempt">Befreit</option>
                </select>
            </div>

            <div class="col-span-2 md:col-span-1">
                <label class="mb-1 block text-sm font-medium text-slate-700">
                    Suche
                </label>

                <input
                    type="search"
                    wire:model.live.debounce.300ms="search"
                    placeholder="Mitglied suchen ..."
                    class="w-full rounded-lg border border-slate-300 px-3 py-2"
                >
            </div>

            <div class="col-span-2 md:col-span-1">
                <button
                    type="button"
                    wire:click="$set('showCreateYear', true)"
                    class="w-full rounded-lg bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-800 md:w-auto"
                >
                    + Beitragsjahr anlegen
                </button>
            </div>


        </div>

        @if($yearId)

            <div
                x-data="{ open: window.matchMedia('(min-width: 768px)').matches }"
                class="border-t border-slate-200 p-4 sm:p-6"
            >

                <button
                    type="button"
                    @click="open = !open"
                    class="flex w-full items-center justify-between text-left text-sm font-semibold text-slate-900"
                >
                    Erinnerungseinstellungen
                    <span x-text="open ? '▴' : '▾'" class="text-slate-500"></span>
                </button>

                <div x-show="open" x-collapse>

                <div class="mt-4 grid gap-4 md:grid-cols-3">

                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">
                            Erste Erinnerung nach Tagen
                        </label>

                        <input
                            type="number"
                            min="0"
                            wire:model="firstReminderAfterDays"
                            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                        >

                        @error('firstReminderAfterDays')
                        <p class="mt-1 text-xs text-red-600">
                            {{ $message }}
                        </p>
                        @enderror
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">
                            Abstand zwischen Erinnerungen
                        </label>

                        <input
                            type="number"
                            min="1"
                            wire:model="reminderIntervalDays"
                            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                        >

                        @error('reminderIntervalDays')
                        <p class="mt-1 text-xs text-red-600">
                            {{ $message }}
                        </p>
                        @enderror
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">
                            Maximale Erinnerungen
                        </label>

                        <input
                            type="number"
                            min="1"
                            max="10"
                            wire:model="maxReminders"
                            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                        >

                        @error('maxReminders')
                        <p class="mt-1 text-xs text-red-600">
                            {{ $message }}
                        </p>
                        @enderror
                    </div>

                </div>

                <div class="mt-4">
                    <button
                        type="button"
                        wire:click="saveReminderSettings"
                        class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800"
                    >
                        Erinnerungseinstellungen speichern
                    </button>
                </div>

                </div>

            </div>

        @endif
    </section>

    @if($showCreateYear)

        <section class="rounded-xl border border-slate-200 bg-white shadow-sm">

            <div class="border-b border-slate-200 px-6 py-4">
                <h3 class="font-semibold text-slate-900">
                    Neues Beitragsjahr
                </h3>
            </div>

            <div class="grid gap-4 p-6 md:grid-cols-4">

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">
                        Jahr
                    </label>

                    <input
                        type="number"
                        wire:model="newYear"
                        class="w-full rounded-lg border border-slate-300 px-3 py-2"
                        placeholder="2027"
                    >
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">
                        Bezeichnung
                    </label>

                    <input
                        type="text"
                        wire:model="newYearName"
                        class="w-full rounded-lg border border-slate-300 px-3 py-2"
                        placeholder="Mitgliedsbeitrag 2027"
                    >
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">
                        Standardbetrag
                    </label>

                    <input
                        type="text"
                        wire:model="newYearAmount"
                        class="w-full rounded-lg border border-slate-300 px-3 py-2"
                        placeholder="90,00"
                    >
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">
                        Fälligkeit
                    </label>

                    <input
                        type="date"
                        wire:model="newYearDueDate"
                        class="w-full rounded-lg border border-slate-300 px-3 py-2"
                    >
                </div>

            </div>

            <div class="flex justify-end gap-2 border-t border-slate-200 bg-slate-50 px-6 py-4">

                <button
                    type="button"
                    wire:click="$set('showCreateYear', false)"
                    class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700"
                >
                    Abbrechen
                </button>

                <button
                    type="button"
                    wire:click="createYear"
                    class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white"
                >
                    Beitragsjahr anlegen
                </button>

            </div>

        </section>

    @endif

    <section
        x-data="{ open: window.matchMedia('(min-width: 768px)').matches }"
        class="rounded-xl border border-slate-200 bg-white shadow-sm"
    >

        <button
            type="button"
            @click="open = !open"
            class="flex w-full items-center justify-between border-b border-slate-200 px-4 py-4 text-left sm:px-6"
        >
            <span>
                <span class="block font-semibold text-slate-900">
                    Standardbeitrag
                </span>

                <span class="mt-1 block text-sm text-slate-500">
                    Standardbetrag für das ausgewählte Beitragsjahr.
                </span>
            </span>

            <span x-text="open ? '▴' : '▾'" class="text-slate-500"></span>
        </button>

        <div x-show="open" x-collapse class="flex flex-col gap-4 p-4 sm:flex-row sm:flex-wrap sm:items-end sm:p-6">

            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">
                    Betrag
                </label>

                <div class="flex items-center gap-2">
                    <input
                        type="text"
                        wire:model="defaultAmount"
                        class="w-32 rounded-lg border border-slate-300 px-3 py-2 text-right"
                        placeholder="0,00"
                        @disabled(!$selectedYear->active)
                    >

                    <span class="text-sm text-slate-500">
                    €
                </span>
                </div>

                @error('defaultAmount')
                <div class="mt-1 text-sm text-red-600">
                    {{ $message }}
                </div>
                @enderror
            </div>

            <button
                type="button"
                wire:click="saveDefaultAmount"
                class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
                @disabled(!$selectedYear->active)
            >
                Standardbetrag speichern
            </button>

            <button
                type="button"
                wire:click="applyDefaultAmount"
                wire:confirm="Standardbetrag wirklich auf alle offenen Beiträge dieses Jahres anwenden?"
                class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800"
                @disabled(!$selectedYear->active)
            >
                Auf offene Beiträge anwenden
            </button>

            @if($selectedYear)

                <a
                    href="{{ route(
                        'membership-fees.prescriptions.all',
                        $selectedYear->yearID
                    ) }}"
                    target="_blank"
                    class="rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50"
                >
                    Alle offenen Vorschreibungen
                </a>

            @endif

        </div>

    </section>

    @if($selectedYear)

        <div class="grid grid-cols-3 gap-2 sm:gap-4">

            <div class="rounded-xl border border-slate-200 bg-white p-3 shadow-sm sm:p-5">
                <div class="text-xs text-slate-500 sm:text-sm">
                    Offen
                </div>

                <div class="mt-1 text-xl font-bold sm:text-3xl">
                    {{ $openCount }}
                </div>
            </div>

            <div class="rounded-xl border border-slate-200 bg-white p-3 shadow-sm sm:p-5">
                <div class="text-xs text-slate-500 sm:text-sm">
                    Bezahlt
                </div>

                <div class="mt-1 text-xl font-bold sm:text-3xl">
                    {{ $paidCount }}
                </div>
            </div>

            <div class="rounded-xl border border-slate-200 bg-white p-3 shadow-sm sm:p-5">
                <div class="text-xs text-slate-500 sm:text-sm">
                    Standardbeitrag
                </div>

                <div class="mt-1 text-xl font-bold sm:text-3xl">
                    @if($selectedYear->default_amount !== null)
                        {{ number_format((float) $selectedYear->default_amount, 2, ',', '.') }} €
                    @else
                        –
                    @endif
                </div>
            </div>

        </div>

        <section class="rounded-xl border border-slate-200 bg-white shadow-sm">

            <div>

                <div
                    x-data="{ open: window.matchMedia('(min-width: 768px)').matches }"
                    class="border-b border-slate-200"
                >

                <button
                    type="button"
                    @click="open = !open"
                    class="flex w-full items-center justify-between px-4 py-4 text-left text-sm font-semibold text-slate-900 md:hidden"
                >
                    <span>
                        Versand &amp; Auswahl
                        <span class="ml-1 font-normal text-slate-500">({{ count($selectedEntries) }} ausgewählt)</span>
                    </span>
                    <span x-text="open ? '▴' : '▾'" class="text-slate-500"></span>
                </button>

                <div x-show="open" x-collapse class="grid grid-cols-1 gap-2 px-4 pb-4 sm:grid-cols-2 md:grid-cols-4 md:gap-4 md:p-6">
                    <button
                        type="button"
                        wire:click="selectAllOpen"
                        class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
                    >
                        Alle offenen auswählen
                    </button>

                    <button
                        type="button"
                        wire:click="clearSelection"
                        class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
                    >
                        Auswahl aufheben
                    </button>

                    <span class="ml-2 text-sm text-slate-500">
                        {{ count($selectedEntries) }} ausgewählt
                    </span>
                    <button
                        type="button"
                        wire:click="downloadSelected"
                        @disabled(count($selectedEntries) === 0)
                        class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        Auswahl als PDF
                    </button>


                        <button
                            type="button"
                            wire:click="$set('emailFilter', 'all')"
                            class="rounded-lg px-4 py-2 text-sm font-medium
                            {{ $emailFilter === 'all'
                                ? 'bg-slate-900 text-white'
                                : 'border border-slate-300 bg-white text-slate-700 hover:bg-slate-50'
                            }}"
                        >
                            Alle
                        </button>

                        <button
                            type="button"
                            wire:click="$set('emailFilter', 'with_email')"
                            class="rounded-lg px-4 py-2 text-sm font-medium
                            {{ $emailFilter === 'with_email'
                                ? 'bg-slate-900 text-white'
                                : 'border border-slate-300 bg-white text-slate-700 hover:bg-slate-50'
                            }}"
                        >
                            Mit E-Mail
                        </button>

                        <button
                            type="button"
                            wire:click="$set('emailFilter', 'without_email')"
                            class="rounded-lg px-4 py-2 text-sm font-medium
                            {{ $emailFilter === 'without_email'
                                ? 'bg-slate-900 text-white'
                                : 'border border-slate-300 bg-white text-slate-700 hover:bg-slate-50'
                            }}"
                        >
                            Ohne E-Mail
                        </button>

                    <button
                        type="button"
                        wire:click="sendSelectedByEmail"
                        @disabled($this->countSelectedWithEmail() === 0)
                        class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        Auswahl per E-Mail senden
                    </button>

                    <button
                        type="button"
                        wire:click="selectAllDueReminders"
                        class="rounded-lg border border-amber-300 bg-amber-50 px-4 py-2 text-sm font-medium text-amber-800 hover:bg-amber-100"
                    >
                        Alle fälligen Erinnerungen auswählen
                    </button>

                    <button
                        type="button"
                        wire:click="requestSelectedReminders"
                        @disabled(count($selectedEntries) === 0)
                        class="rounded-lg bg-amber-600 px-4 py-2 text-sm font-semibold text-white hover:bg-amber-700 disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        Auswahl als Erinnerung senden
                    </button>

                    <button
                        type="button"
                        wire:click="downloadSelectedReminders"
                        @disabled(count($selectedEntries) === 0)
                        class="rounded-lg border border-amber-300 bg-amber-50 px-4 py-2 text-sm font-semibold text-amber-800 hover:bg-amber-100 disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        Auswahl als Erinnerungs-PDF
                    </button>

                    @php
                        $reminderSummary = $this->reminderSelectionSummary();
                    @endphp

                    @if($reminderSummary['selected'] > 0)
                        <div class="text-sm text-slate-500">
                            {{ $reminderSummary['selected'] }} ausgewählt,
                            {{ $reminderSummary['sendable'] }} erhalten eine Erinnerung,
                            {{ $reminderSummary['skipped'] }} werden übersprungen.
                        </div>
                    @endif

                </div>

                </div>

                <div class="flex flex-wrap items-center gap-2 p-4 md:p-6">

                    <button
                        type="button"
                        wire:click="$set('reminderFilter', 'all')"
                        class="rounded-lg px-4 py-2 text-sm font-medium
                    {{ $reminderFilter === 'all'
                        ? 'bg-slate-900 text-white'
                        : 'border border-slate-300 bg-white text-slate-700 hover:bg-slate-50'
                    }}"
                    >
                        Filter alle
                    </button>

                    <button
                        type="button"
                        wire:click="$set('reminderFilter', 'due')"
                        class="rounded-lg px-4 py-2 text-sm font-medium
                        {{ $reminderFilter === 'due'
                            ? 'bg-amber-600 text-white'
                            : 'border border-amber-300 bg-amber-50 text-amber-800 hover:bg-amber-100'
                        }}"
                    >
                        Filter Erinnerung fällig
                    </button>

                    <button
                        type="button"
                        wire:click="$set('reminderFilter', 'sent')"
                        class="rounded-lg px-4 py-2 text-sm font-medium
                            {{ $reminderFilter === 'sent'
                                ? 'bg-blue-600 text-white'
                                : 'border border-blue-300 bg-blue-50 text-blue-800 hover:bg-blue-100'
                            }}"
                    >
                        Filter Bereits erinnert
                    </button>

                </div>

                <div class="hidden overflow-x-auto md:block">

                <table class="min-w-full divide-y divide-slate-200">

                    <thead class="bg-slate-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                            Mitglied
                        </th>

                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                            Betrag
                        </th>

                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                            Status
                        </th>

                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                            Bezahlt am
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                            Versandstatus
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                            Erinnerung
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">

                        </th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                            Auswahl
                        </th>
                    </tr>
                    </thead>

                    <tbody class="divide-y divide-slate-100">

                    @forelse($entries as $entry)

                        <tr wire:key="entry-row-{{ $entry->entryID }}" class="hover:bg-slate-50">

                            <td class="px-6 py-4">

                                <div class="font-medium text-slate-900">
                                    {{ $entry->member?->surname }}
                                    {{ $entry->member?->name }}
                                </div>

                                <div class="text-xs text-slate-400">
                                    #{{ $entry->memberID }}
                                </div>

                            </td>

                            <td class="px-6 py-4">

                                <div class="flex items-center gap-2">

                                    <input
                                        type="text"
                                        value="{{ $entry->amount !== null
                                            ? number_format((float) $entry->amount, 2, ',', '')
                                            : '' }}"
                                                                            wire:change="updateAmount(
                                            {{ $entry->entryID }},
                                            $event.target.value
                                        )"
                                        @disabled(!$selectedYear->active)
                                        class="w-28 rounded-lg border border-slate-300 px-3 py-2 text-sm text-right disabled:bg-slate-100 disabled:text-slate-500"
                                    >

                                    <span class="text-sm text-slate-500">
                                        €
                                    </span>

                                </div>

                            </td>

                            <td class="px-6 py-4">

                                <select
                                    wire:change="updateStatus(
                                        {{ $entry->entryID }},
                                        $event.target.value
                                    )"
                                    @disabled(!$selectedYear->active)
                                    class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm disabled:bg-slate-100"
                                >
                                    <option
                                        value="open"
                                        @selected($entry->status === 'open')
                                    >
                                        Offen
                                    </option>

                                    <option
                                        value="paid"
                                        @selected($entry->status === 'paid')
                                    >
                                        Bezahlt
                                    </option>

                                    <option
                                        value="exempt"
                                        @selected($entry->status === 'exempt')
                                    >
                                        Befreit
                                    </option>
                                </select>

                            </td>

                            <td class="px-6 py-4 text-sm text-slate-600">

                                {{ $entry->paid_at?->format('d.m.Y') ?? '–' }}

                            </td>
                            <td class="px-6 py-4 text-sm text-slate-600">
                                @php
                                    $lastPrescription = $entry->prescriptions
                                        ->where('type', 'prescription')
                                        ->sortByDesc('sent_at')
                                        ->first();

                                    $lastReminder = $entry->prescriptions
                                        ->where('type', 'reminder')
                                        ->sortByDesc('reminder_level')
                                        ->first();
                                @endphp

                                @if($lastReminder)

                                    <span class="rounded-full bg-amber-100 px-2.5 py-1 text-xs font-medium text-amber-800">
                                        {{ $lastReminder->reminder_level }}. Erinnerung versendet
                                    </span>

                                                            @elseif($lastPrescription)

                                                                <span class="rounded-full bg-blue-100 px-2.5 py-1 text-xs font-medium text-blue-800">
                                        Vorschreibung versendet
                                    </span>

                                                            @else

                                                                <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-600">
                                        Noch nicht versendet
                                    </span>

                                @endif
                            </td>

                            <td class="px-4 py-3">
                                @php
                                    $year = $entry->year;

                                    $reminders = $entry->prescriptions
                                        ->where('type', 'reminder')
                                        ->whereNotNull('sent_at');

                                    $reminderCount = $reminders->count();

                                    $lastReminder = $reminders
                                        ->sortByDesc('sent_at')
                                        ->first();

                                    $prescription = $entry->prescriptions
                                        ->where('type', 'prescription')
                                        ->whereNotNull('sent_at')
                                        ->sortByDesc('sent_at')
                                        ->first();

                                    $nextReminderDate = null;

                                    if (
                                        $entry->status === 'open'
                                        && $year
                                        && $year->due_date
                                        && $prescription
                                        && $reminderCount < $year->max_reminders
                                    ) {
                                        if ($reminderCount === 0) {
                                            $nextReminderDate = $year->due_date
                                                ->copy()
                                                ->addDays($year->first_reminder_after_days);
                                        } elseif ($lastReminder) {
                                            $nextReminderDate = $lastReminder->sent_at
                                                ->copy()
                                                ->addDays($year->reminder_interval_days);
                                        }
                                    }
                                @endphp

                                @if($entry->status !== 'open')

                                    <span class="text-xs text-slate-400">
                                        Nicht erforderlich
                                    </span>

                                                            @elseif(!$prescription)

                                                                <span class="text-xs text-slate-500">
                                        Vorschreibung fehlt
                                    </span>

                                                            @elseif($reminderCount >= $year->max_reminders)

                                                                <span class="rounded-full bg-red-100 px-2.5 py-1 text-xs font-medium text-red-800">
                                        Max. Erinnerungen erreicht
                                    </span>

                                                            @elseif($entry->isReminderDue())

                                                                <span class="rounded-full bg-amber-100 px-2.5 py-1 text-xs font-medium text-amber-800">
                                        Erinnerung fällig
                                    </span>

                                                            @elseif($nextReminderDate)

                                                                <span class="text-xs text-slate-500">
                                        ab {{ $nextReminderDate->format('d.m.Y') }}
                                    </span>

                                @endif
                            </td>

                            <td class="px-6 py-4 text-sm text-slate-600 text-right">

                                <a
                                    href="{{ route(
                                        'membership-fees.prescription',
                                        $entry->entryID
                                    ) }}"
                                    target="_blank"
                                    class="inline-flex rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
                                >
                                    Vorschreibung PDF
                                </a>

                            </td>
                            <td class="px-6 py-4 text-sm text-slate-600">
                                <input
                                    type="checkbox"
                                    wire:model.live="selectedEntries"
                                    value="{{ $entry->entryID }}"
                                    class="h-4 w-4 rounded border-slate-300"
                                >
                            </td>

                        </tr>

                    @empty

                        <tr>
                            <td
                                colspan="8"
                                class="px-6 py-12 text-center text-sm text-slate-500"
                            >
                                Keine Beiträge für dieses Jahr gefunden.
                            </td>
                        </tr>

                    @endforelse


                    </tbody>

                </table>

                </div>

                {{-- Mobile: Kartenansicht mit großen Schaltflächen zum Bezahlt-Markieren --}}
                <div class="divide-y divide-slate-100 md:hidden">

                    @forelse($entries as $entry)

                        @php
                            $hasReminder = $entry->prescriptions
                                ->where('type', 'reminder')
                                ->whereNotNull('sent_at')
                                ->isNotEmpty();

                            $hasPrescription = $entry->prescriptions
                                ->where('type', 'prescription')
                                ->whereNotNull('sent_at')
                                ->isNotEmpty();
                        @endphp

                        <div
                            wire:key="entry-card-{{ $entry->entryID }}"
                            class="p-4 {{ $entry->status === 'paid' ? 'bg-emerald-50/60' : '' }}"
                        >

                            <div class="flex items-start gap-3">

                                <input
                                    type="checkbox"
                                    wire:model.live="selectedEntries"
                                    value="{{ $entry->entryID }}"
                                    class="mt-1 h-5 w-5 shrink-0 rounded border-slate-300"
                                    aria-label="Auswählen"
                                >

                                <div class="min-w-0 flex-1">

                                    <div class="flex items-start justify-between gap-2">

                                        <div class="min-w-0">
                                            <div class="truncate font-medium text-slate-900">
                                                {{ $entry->member?->surname }}
                                                {{ $entry->member?->name }}
                                            </div>

                                            <div class="text-xs text-slate-400">
                                                #{{ $entry->memberID }}
                                            </div>
                                        </div>

                                        <div class="shrink-0 text-right">
                                            <div class="font-semibold text-slate-900">
                                                {{ $entry->amount !== null
                                                    ? number_format((float) $entry->amount, 2, ',', '.').' €'
                                                    : '–' }}
                                            </div>

                                            @if($entry->status === 'paid')
                                                <span class="text-xs font-medium text-emerald-700">
                                                    Bezahlt {{ $entry->paid_at?->format('d.m.Y') }}
                                                </span>
                                            @elseif($entry->status === 'exempt')
                                                <span class="text-xs font-medium text-slate-500">
                                                    Befreit
                                                </span>
                                            @else
                                                <span class="text-xs font-medium text-amber-700">
                                                    Offen
                                                </span>
                                            @endif
                                        </div>

                                    </div>

                                    <div class="mt-2 flex flex-wrap gap-1.5">
                                        @if($hasReminder)
                                            <span class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800">
                                                Erinnert
                                            </span>
                                        @elseif($hasPrescription)
                                            <span class="rounded-full bg-blue-100 px-2 py-0.5 text-xs font-medium text-blue-800">
                                                Vorschreibung versendet
                                            </span>
                                        @else
                                            <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600">
                                                Noch nicht versendet
                                            </span>
                                        @endif

                                        @if($entry->status === 'open' && $entry->isReminderDue())
                                            <span class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800">
                                                Erinnerung fällig
                                            </span>
                                        @endif
                                    </div>

                                </div>

                            </div>

                            @if($selectedYear->active)

                                <div class="mt-3 flex gap-2">

                                    @if($entry->status === 'open')

                                        <button
                                            type="button"
                                            wire:click="updateStatus({{ $entry->entryID }}, 'paid')"
                                            class="flex-1 whitespace-nowrap rounded-lg bg-emerald-600 px-4 py-3 text-base font-semibold text-white active:bg-emerald-700"
                                        >
                                            ✓ Bezahlt
                                        </button>

                                        <button
                                            type="button"
                                            wire:click="updateStatus({{ $entry->entryID }}, 'exempt')"
                                            wire:confirm="{{ $entry->member?->full_name }} vom Beitrag befreien?"
                                            class="rounded-lg border border-slate-300 bg-white px-3 py-3 text-sm font-medium text-slate-700 active:bg-slate-100"
                                        >
                                            Befreien
                                        </button>

                                    @else

                                        <button
                                            type="button"
                                            wire:click="updateStatus({{ $entry->entryID }}, 'open')"
                                            wire:confirm="Beitrag von {{ $entry->member?->full_name }} wieder auf offen setzen?"
                                            class="flex-1 rounded-lg border border-slate-300 bg-white px-4 py-3 text-sm font-medium text-slate-700 active:bg-slate-100"
                                        >
                                            Zurück auf offen
                                        </button>

                                    @endif

                                    <a
                                        href="{{ route('membership-fees.prescription', $entry->entryID) }}"
                                        target="_blank"
                                        class="rounded-lg border border-slate-300 bg-white px-3 py-3 text-sm font-medium text-slate-700 active:bg-slate-100"
                                        aria-label="Vorschreibung PDF"
                                    >
                                        PDF
                                    </a>

                                </div>

                            @endif

                        </div>

                    @empty

                        <p class="px-4 py-12 text-center text-sm text-slate-500">
                            Keine Beiträge für dieses Jahr gefunden.
                        </p>

                    @endforelse

                </div>

            </div>

        </section>

    @endif

    @if($showResendDialog)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
            <div class="w-full max-w-md rounded-xl bg-white p-6 shadow-xl">

                <h3 class="text-lg font-semibold text-slate-900">
                    Vorschreibung bereits versendet
                </h3>

                <p class="mt-2 text-sm text-slate-600">
                    Für dieses Mitglied wurde bereits eine Beitragsvorschreibung versendet.
                </p>

                <div class="mt-6 flex flex-col gap-2">

                    <button
                        type="button"
                        wire:click="resendAsPrescription"
                        class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white"
                    >
                        Trotzdem erneut senden
                    </button>

                    <button
                        type="button"
                        wire:click="sendAsReminder"
                        class="rounded-lg bg-amber-600 px-4 py-2 text-sm font-semibold text-white"
                    >
                        Als Erinnerung senden
                    </button>

                    <button
                        type="button"
                        wire:click="$set('showResendDialog', false)"
                        class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700"
                    >
                        Abbrechen
                    </button>

                </div>

            </div>
        </div>
    @endif

    @if($showReminderConfirmDialog)
        @php
            $summary = $this->reminderSelectionSummary();
        @endphp

        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
            <div class="w-full max-w-md rounded-xl bg-white p-6 shadow-xl">

                <h3 class="text-lg font-semibold text-slate-900">
                    Erinnerungen versenden?
                </h3>

                <div class="mt-4 space-y-2 text-sm text-slate-600">
                    <p>
                        <strong>{{ $summary['selected'] }}</strong>
                        Beiträge wurden ausgewählt.
                    </p>

                    <p>
                        <strong>{{ $summary['sendable'] }}</strong>
                        Erinnerung(en) werden versendet.
                    </p>

                    @if($summary['skipped'] > 0)
                        <p class="text-amber-700">
                            <strong>{{ $summary['skipped'] }}</strong>
                            werden übersprungen.
                        </p>
                    @endif
                </div>

                <div class="mt-6 flex justify-end gap-2">
                    <button
                        type="button"
                        wire:click="$set('showReminderConfirmDialog', false)"
                        class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700"
                    >
                        Abbrechen
                    </button>

                    <button
                        type="button"
                        wire:click="sendSelectedReminders"
                        class="rounded-lg bg-amber-600 px-4 py-2 text-sm font-semibold text-white hover:bg-amber-700"
                    >
                        Erinnerungen jetzt senden
                    </button>
                </div>

            </div>
        </div>
    @endif
    <div
        wire:loading.flex
        wire:target="
        sendSelectedByEmail,
        sendSelectedReminders,
        resendAsPrescription,
        sendAsReminder,
        selectAllOpen,
        selectAllDueReminders,
        downloadSelected,
        downloadSelectedReminders,
        saveReminderSettings,
        saveDefaultAmount,
        applyDefaultAmount,
        createYear,
        toggleYearActive
    "
        class="fixed inset-0 z-[9999] hidden items-center justify-center bg-slate-900/30 backdrop-blur-sm"
    >
        <div class="flex items-center gap-3 rounded-xl bg-white px-6 py-4 shadow-xl">

            <svg
                class="h-5 w-5 animate-spin text-slate-700"
                xmlns="http://www.w3.org/2000/svg"
                fill="none"
                viewBox="0 0 24 24"
            >
                <circle
                    class="opacity-25"
                    cx="12"
                    cy="12"
                    r="10"
                    stroke="currentColor"
                    stroke-width="4"
                ></circle>

                <path
                    class="opacity-75"
                    fill="currentColor"
                    d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"
                ></path>
            </svg>

            <div>
                <div class="text-sm font-semibold text-slate-800">
                    Bitte warten …
                </div>

                <div class="text-xs text-slate-500">
                    Vorgang wird verarbeitet.
                </div>
            </div>

        </div>
    </div>

</div>


