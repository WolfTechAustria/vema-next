@php
    $money = fn ($value) => number_format((float) $value, 2, ',', '.') . ' €';
    $isOpen = $selectedYear && !$selectedYear->isClosed();
@endphp

<div class="space-y-6 {{ $isOpen ? 'pb-24 md:pb-0' : '' }}">

    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h2 class="text-2xl font-bold tracking-tight">
                Kassabuch
            </h2>

            @include('partials.flash-messages')

            <p class="mt-1 text-sm text-slate-500">
                Einnahmen und Ausgaben mit Belegen je Vereinsjahr.
            </p>
        </div>

        @if($isOpen)
            <div class="hidden gap-2 md:flex">
                <button
                    type="button"
                    wire:click="openCreate('income')"
                    class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700"
                >
                    + Einnahme
                </button>

                <button
                    type="button"
                    wire:click="openCreate('expense')"
                    class="rounded-lg bg-rose-600 px-4 py-2 text-sm font-medium text-white hover:bg-rose-700"
                >
                    + Ausgabe
                </button>
            </div>
        @endif
    </div>

    @include('partials.cash-book-nav')

    @error('year')
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-800">
            {{ $message }}
        </div>
    @enderror

    @if(!$selectedYear)

        <section class="rounded-xl border border-dashed border-slate-300 bg-white p-8 text-center">
            <div class="text-3xl">📒</div>

            <h3 class="mt-2 font-semibold text-slate-900">
                Noch kein Vereinsjahr angelegt
            </h3>

            <p class="mt-1 text-sm text-slate-500">
                Lege zuerst ein Vereinsjahr mit Zeitraum und Anfangsbestand an.
            </p>

            <a
                href="{{ route('cash-book.years') }}"
                wire:navigate
                class="mt-4 inline-flex rounded-lg bg-slate-900 px-4 py-2.5 text-sm font-medium text-white hover:bg-slate-800"
            >
                Vereinsjahr anlegen
            </a>
        </section>

    @else

        {{-- Jahr + Status --}}
        <div class="flex flex-wrap items-center gap-2">
            <select
                wire:model.live="yearId"
                class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-base sm:w-auto sm:py-2 sm:text-sm"
                aria-label="Vereinsjahr"
            >
                @foreach($years as $year)
                    <option value="{{ $year->cashBookYearID }}">
                        Vereinsjahr {{ $year->name }}
                        ({{ $year->start_date->format('d.m.Y') }} – {{ $year->end_date->format('d.m.Y') }})
                    </option>
                @endforeach
            </select>

            @if($selectedYear->isClosed())
                <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600">
                    Abgeschlossen{{ $selectedYear->general_meeting_date ? ' (JHV ' . $selectedYear->general_meeting_date->format('d.m.Y') . ')' : '' }}
                </span>
            @else
                <span class="rounded-full bg-green-50 px-2.5 py-1 text-xs font-semibold text-green-700">
                    Offen
                </span>
            @endif

            <a
                href="{{ route('cash-book.report', $selectedYear->cashBookYearID) }}"
                target="_blank"
                class="ml-auto inline-flex items-center rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
            >
                PDF Kassabericht
            </a>
        </div>

        {{-- Kennzahlen --}}
        <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
            <div class="col-span-2 rounded-xl border border-slate-200 bg-slate-900 p-4 text-white shadow-sm md:order-last md:col-span-1">
                <div class="text-xs font-medium uppercase tracking-wide text-slate-300">
                    Kassastand
                </div>
                <div class="mt-1 text-2xl font-bold {{ $balance < 0 ? 'text-rose-300' : '' }}">
                    {{ $money($balance) }}
                </div>
            </div>

            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <div class="text-xs font-medium uppercase tracking-wide text-slate-500">
                    Einnahmen
                </div>
                <div class="mt-1 text-lg font-semibold text-emerald-700">
                    {{ $money($totals['income']) }}
                </div>
            </div>

            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <div class="text-xs font-medium uppercase tracking-wide text-slate-500">
                    Ausgaben
                </div>
                <div class="mt-1 text-lg font-semibold text-rose-700">
                    {{ $money($totals['expense']) }}
                </div>
            </div>

            <div class="col-span-2 rounded-xl border border-slate-200 bg-white p-4 shadow-sm md:order-first md:col-span-1">
                <div class="text-xs font-medium uppercase tracking-wide text-slate-500">
                    Anfangsbestand
                </div>
                <div class="mt-1 text-lg font-semibold text-slate-900">
                    {{ $money($selectedYear->opening_balance) }}
                </div>
            </div>
        </div>

        {{-- Buchungen --}}
        <section class="rounded-xl border border-slate-200 bg-white shadow-sm">

            <div
                x-data="{ open: window.matchMedia('(min-width: 768px)').matches }"
                class="border-b border-slate-200 p-4 sm:p-6"
            >
                <div class="flex items-center gap-2">
                    <input
                        type="search"
                        wire:model.live.debounce.300ms="search"
                        placeholder="Suche (Text, Kategorie, Beleg-Nr.) ..."
                        class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-base sm:py-2 sm:text-sm"
                    >

                    <button
                        type="button"
                        x-on:click="open = !open"
                        class="shrink-0 rounded-lg border border-slate-300 px-3 py-2.5 text-sm font-medium text-slate-700 md:hidden"
                    >
                        Filter
                    </button>
                </div>

                <div x-show="open" x-collapse class="mt-3 grid grid-cols-2 gap-3 md:grid-cols-4">
                    <select
                        wire:model.live="typeFilter"
                        class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-base sm:py-2 sm:text-sm"
                        aria-label="Art"
                    >
                        <option value="all">Alle Buchungen</option>
                        <option value="income">Nur Einnahmen</option>
                        <option value="expense">Nur Ausgaben</option>
                    </select>

                    <select
                        wire:model.live="categoryFilter"
                        class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-base sm:py-2 sm:text-sm"
                        aria-label="Kategorie"
                    >
                        <option value="">Alle Kategorien</option>
                        @foreach($categories as $categoryOption)
                            <option value="{{ $categoryOption }}">{{ $categoryOption }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            {{-- Desktop: Tabelle --}}
            <div class="hidden overflow-x-auto md:block">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-slate-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Nr.</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Datum</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Beschreibung</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Kategorie</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wide text-slate-500">Beleg</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Betrag</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                    </thead>

                    <tbody class="divide-y divide-slate-100">
                    @forelse($entries as $entry)
                        <tr wire:key="entry-row-{{ $entry->cashBookEntryID }}" class="hover:bg-slate-50">
                            <td class="px-4 py-3 text-sm text-slate-500">#{{ $entry->receipt_number }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-sm text-slate-700">{{ $entry->date->format('d.m.Y') }}</td>
                            <td class="px-4 py-3">
                                <div class="font-medium text-slate-900">{{ $entry->description }}</div>
                                @if($entry->note)
                                    <div class="text-xs text-slate-500">{{ \Illuminate\Support\Str::limit($entry->note, 80) }}</div>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-sm text-slate-600">{{ $entry->category ?? '–' }}</td>
                            <td class="px-4 py-3 text-center text-sm">
                                @if($entry->attachments_count > 0)
                                    <span title="{{ $entry->attachments_count }} Beleg(e)">📎 {{ $entry->attachments_count }}</span>
                                @else
                                    <span class="text-xs text-amber-600">fehlt</span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-right font-semibold {{ $entry->isIncome() ? 'text-emerald-700' : 'text-rose-700' }}">
                                {{ $entry->isIncome() ? '+' : '−' }} {{ $money($entry->amount) }}
                            </td>
                            <td class="px-4 py-3 text-right">
                                <button
                                    type="button"
                                    wire:click="openEdit({{ $entry->cashBookEntryID }})"
                                    class="rounded-md px-2 py-1 text-sm font-medium text-slate-700 hover:bg-slate-100"
                                >
                                    {{ $isOpen ? 'Bearbeiten' : 'Ansehen' }}
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-10 text-center text-sm text-slate-500">
                                Keine Buchungen gefunden.
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Mobile: Karten, ganze Karte antippbar --}}
            <div class="divide-y divide-slate-100 md:hidden">
                @forelse($entries as $entry)
                    <button
                        type="button"
                        wire:key="entry-card-{{ $entry->cashBookEntryID }}"
                        wire:click="openEdit({{ $entry->cashBookEntryID }})"
                        class="flex w-full items-start gap-3 p-4 text-left active:bg-slate-50"
                    >
                        <span class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-sm font-bold
                            {{ $entry->isIncome() ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700' }}">
                            {{ $entry->isIncome() ? '+' : '−' }}
                        </span>

                        <span class="min-w-0 flex-1">
                            <span class="block truncate font-medium text-slate-900">{{ $entry->description }}</span>
                            <span class="mt-0.5 block text-xs text-slate-500">
                                #{{ $entry->receipt_number }} · {{ $entry->date->format('d.m.Y') }}{{ $entry->category ? ' · ' . $entry->category : '' }}
                            </span>
                        </span>

                        <span class="shrink-0 text-right">
                            <span class="block font-semibold {{ $entry->isIncome() ? 'text-emerald-700' : 'text-rose-700' }}">
                                {{ $money($entry->amount) }}
                            </span>
                            <span class="block text-xs {{ $entry->attachments_count > 0 ? 'text-slate-500' : 'text-amber-600' }}">
                                {{ $entry->attachments_count > 0 ? '📎 ' . $entry->attachments_count : 'ohne Beleg' }}
                            </span>
                        </span>
                    </button>
                @empty
                    <div class="px-4 py-10 text-center text-sm text-slate-500">
                        Keine Buchungen gefunden.
                    </div>
                @endforelse
            </div>
        </section>

        {{-- Mobile: Schnellerfassung unten fixiert --}}
        @if($isOpen)
            <div class="fixed inset-x-0 bottom-0 z-30 grid grid-cols-2 gap-2 border-t border-slate-200 bg-white/95 p-3 backdrop-blur md:hidden"
                 style="padding-bottom: max(0.75rem, env(safe-area-inset-bottom));">
                <button
                    type="button"
                    wire:click="openCreate('income')"
                    class="rounded-xl bg-emerald-600 py-3.5 text-base font-semibold text-white active:bg-emerald-700"
                >
                    + Einnahme
                </button>

                <button
                    type="button"
                    wire:click="openCreate('expense')"
                    class="rounded-xl bg-rose-600 py-3.5 text-base font-semibold text-white active:bg-rose-700"
                >
                    + Ausgabe
                </button>
            </div>
        @endif

    @endif

    {{-- Erfassen / Bearbeiten --}}
    @if($showEntryDialog)
        @php
            $readOnly = !$isOpen;
            $existingAttachments = $editingEntry?->attachments ?? collect();
        @endphp

        <div class="fixed inset-0 z-50 flex items-stretch justify-center bg-black/40 sm:items-center sm:p-4">
            <form
                wire:submit="saveEntry"
                class="flex h-full w-full flex-col bg-white shadow-xl sm:h-auto sm:max-h-[90vh] sm:max-w-lg sm:rounded-xl"
            >
                <div class="flex items-center justify-between border-b border-slate-200 px-4 py-3 sm:px-6">
                    <h3 class="text-lg font-semibold text-slate-900">
                        @if($editingEntry)
                            {{ $editingEntry->type->label() }} #{{ $editingEntry->receipt_number }}
                        @else
                            Neue Buchung
                        @endif
                    </h3>

                    <button
                        type="button"
                        wire:click="closeEntryDialog"
                        class="rounded-md px-2 py-1 text-2xl leading-none text-slate-400 hover:text-slate-700"
                        aria-label="Schließen"
                    >
                        ×
                    </button>
                </div>

                <fieldset @disabled($readOnly) class="flex-1 space-y-4 overflow-y-auto px-4 py-4 sm:px-6">

                    {{-- Art --}}
                    <div class="grid grid-cols-2 gap-2">
                        <button
                            type="button"
                            wire:click="$set('type', 'income')"
                            class="rounded-xl border-2 py-3 text-base font-semibold transition
                            {{ $type === 'income' ? 'border-emerald-600 bg-emerald-50 text-emerald-700' : 'border-slate-200 text-slate-500' }}"
                        >
                            Einnahme
                        </button>

                        <button
                            type="button"
                            wire:click="$set('type', 'expense')"
                            class="rounded-xl border-2 py-3 text-base font-semibold transition
                            {{ $type === 'expense' ? 'border-rose-600 bg-rose-50 text-rose-700' : 'border-slate-200 text-slate-500' }}"
                        >
                            Ausgabe
                        </button>
                    </div>
                    @error('type') <p class="text-xs text-red-600">{{ $message }}</p> @enderror

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label for="cb-amount" class="mb-1 block text-sm font-medium text-slate-700">Betrag (€)</label>
                            <input
                                id="cb-amount"
                                type="text"
                                inputmode="decimal"
                                wire:model="amount"
                                placeholder="0,00"
                                autocomplete="off"
                                class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-right text-lg font-semibold"
                            >
                            @error('amount') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="cb-date" class="mb-1 block text-sm font-medium text-slate-700">Datum</label>
                            <input
                                id="cb-date"
                                type="date"
                                wire:model="date"
                                @if($selectedYear)
                                    min="{{ $selectedYear->start_date->toDateString() }}"
                                    max="{{ $selectedYear->end_date->toDateString() }}"
                                @endif
                                class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-base"
                            >
                        </div>
                    </div>
                    @error('date') <p class="-mt-2 text-xs text-red-600">{{ $message }}</p> @enderror

                    <div>
                        <label for="cb-description" class="mb-1 block text-sm font-medium text-slate-700">Beschreibung</label>
                        <input
                            id="cb-description"
                            type="text"
                            wire:model="description"
                            placeholder="z.B. Getränkeeinkauf Sommerfest"
                            class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-base"
                        >
                        @error('description') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="cb-category" class="mb-1 block text-sm font-medium text-slate-700">
                            Kategorie <span class="font-normal text-slate-400">(optional)</span>
                        </label>
                        <input
                            id="cb-category"
                            type="text"
                            list="cb-categories"
                            wire:model="category"
                            placeholder="z.B. Veranstaltung"
                            autocomplete="off"
                            class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-base"
                        >
                        <datalist id="cb-categories">
                            @foreach($categories as $categoryOption)
                                <option value="{{ $categoryOption }}"></option>
                            @endforeach
                        </datalist>
                        @error('category') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="cb-note" class="mb-1 block text-sm font-medium text-slate-700">
                            Notiz <span class="font-normal text-slate-400">(optional)</span>
                        </label>
                        <textarea
                            id="cb-note"
                            wire:model="note"
                            rows="2"
                            class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-base"
                        ></textarea>
                        @error('note') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    {{-- Belege --}}
                    <div class="rounded-xl border border-slate-200 p-3">
                        <div class="mb-2 flex items-center justify-between">
                            <div class="text-sm font-medium text-slate-900">Belege</div>
                            <div class="text-xs text-slate-500">Foto, Scan oder PDF bis 10 MB</div>
                        </div>

                        @if($existingAttachments->isNotEmpty() || count($files) > 0)
                            <div class="mb-3 grid grid-cols-3 gap-2">
                                @foreach($existingAttachments as $attachment)
                                    <div wire:key="att-{{ $attachment->attachmentID }}" class="relative">
                                        <a
                                            href="{{ route('cash-book.attachments.show', $attachment->attachmentID) }}"
                                            target="_blank"
                                            class="flex aspect-square items-center justify-center overflow-hidden rounded-lg border border-slate-200 bg-slate-50"
                                            title="{{ $attachment->file_name }}"
                                        >
                                            @if($attachment->isImage())
                                                <img
                                                    src="{{ route('cash-book.attachments.show', $attachment->attachmentID) }}"
                                                    alt="{{ $attachment->file_name }}"
                                                    class="h-full w-full object-cover"
                                                    loading="lazy"
                                                >
                                            @else
                                                <span class="px-1 text-center text-xs text-slate-600">
                                                    📄<br>{{ \Illuminate\Support\Str::limit($attachment->file_name, 18) }}
                                                </span>
                                            @endif
                                        </a>

                                        @unless($readOnly)
                                            <button
                                                type="button"
                                                wire:click="deleteAttachment({{ $attachment->attachmentID }})"
                                                wire:confirm="Beleg „{{ $attachment->file_name }}“ wirklich löschen?"
                                                class="absolute right-1 top-1 flex h-6 w-6 items-center justify-center rounded-full bg-white/90 text-sm text-red-600 shadow"
                                                aria-label="Beleg löschen"
                                            >
                                                ×
                                            </button>
                                        @endunless
                                    </div>
                                @endforeach

                                @foreach($files as $index => $file)
                                    <div wire:key="new-file-{{ $index }}" class="relative">
                                        <div class="flex aspect-square items-center justify-center overflow-hidden rounded-lg border-2 border-dashed border-emerald-300 bg-emerald-50">
                                            @if(str_starts_with((string) $file->getMimeType(), 'image/') && $file->isPreviewable())
                                                <img src="{{ $file->temporaryUrl() }}" alt="" class="h-full w-full object-cover">
                                            @else
                                                <span class="px-1 text-center text-xs text-slate-600">
                                                    📄<br>{{ \Illuminate\Support\Str::limit($file->getClientOriginalName(), 18) }}
                                                </span>
                                            @endif
                                        </div>

                                        <button
                                            type="button"
                                            wire:click="removeFile({{ $index }})"
                                            class="absolute right-1 top-1 flex h-6 w-6 items-center justify-center rounded-full bg-white/90 text-sm text-red-600 shadow"
                                            aria-label="Datei entfernen"
                                        >
                                            ×
                                        </button>
                                    </div>
                                @endforeach
                            </div>
                        @endif

                        @unless($readOnly)
                            <label class="flex cursor-pointer items-center justify-center gap-2 rounded-lg border border-slate-300 bg-slate-50 px-3 py-3 text-sm font-medium text-slate-700 hover:bg-slate-100">
                                <span wire:loading.remove wire:target="newFiles">📷 Foto aufnehmen / Datei wählen</span>
                                <span wire:loading wire:target="newFiles">Wird hochgeladen …</span>
                                <input
                                    type="file"
                                    wire:model="newFiles"
                                    accept="image/*,application/pdf"
                                    multiple
                                    class="sr-only"
                                >
                            </label>
                        @endunless

                        @error('newFiles.*') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        @error('files.*') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                </fieldset>

                <div class="flex items-center gap-2 border-t border-slate-200 px-4 py-3 sm:px-6"
                     style="padding-bottom: max(0.75rem, env(safe-area-inset-bottom));">
                    @if($editingEntry && !$readOnly)
                        <button
                            type="button"
                            wire:click="deleteEntry({{ $editingEntry->cashBookEntryID }})"
                            wire:confirm="Buchung #{{ $editingEntry->receipt_number }} inkl. Belegen wirklich löschen?"
                            class="rounded-lg px-3 py-2.5 text-sm font-medium text-red-600 hover:bg-red-50"
                        >
                            Löschen
                        </button>
                    @endif

                    <button
                        type="button"
                        wire:click="closeEntryDialog"
                        class="ml-auto rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50"
                    >
                        {{ $readOnly ? 'Schließen' : 'Abbrechen' }}
                    </button>

                    @unless($readOnly)
                        <button
                            type="submit"
                            wire:loading.attr="disabled"
                            wire:target="saveEntry,newFiles"
                            class="rounded-lg bg-slate-900 px-5 py-2.5 text-sm font-semibold text-white hover:bg-slate-800 disabled:opacity-50"
                        >
                            Speichern
                        </button>
                    @endunless
                </div>
            </form>
        </div>
    @endif

</div>
