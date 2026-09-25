@php
    $money = fn ($value) => number_format((float) $value, 2, ',', '.') . ' €';
@endphp

<div class="space-y-6">

    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h2 class="text-2xl font-bold tracking-tight">
                Kassabuch
            </h2>

            @include('partials.flash-messages')

            <p class="mt-1 text-sm text-slate-500">
                Vereinsjahre laufen von Jahreshauptversammlung zu Jahreshauptversammlung.
            </p>
        </div>

        <button
            type="button"
            wire:click="openCreate"
            class="w-full rounded-lg bg-slate-900 px-4 py-2.5 text-sm font-medium text-white hover:bg-slate-800 sm:w-auto sm:py-2"
        >
            + Vereinsjahr
        </button>
    </div>

    @include('partials.cash-book-nav')

    @error('year')
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-800">
            {{ $message }}
        </div>
    @enderror

    <div class="space-y-3">
        @forelse($years as $year)
            <section wire:key="year-{{ $year->cashBookYearID }}" class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:p-6">

                <div class="flex flex-wrap items-start justify-between gap-2">
                    <div>
                        <div class="flex items-center gap-2">
                            <h3 class="text-lg font-semibold text-slate-900">
                                Vereinsjahr {{ $year->name }}
                            </h3>

                            @if($year->isClosed())
                                <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600">Abgeschlossen</span>
                            @else
                                <span class="rounded-full bg-green-50 px-2.5 py-1 text-xs font-semibold text-green-700">Offen</span>
                            @endif
                        </div>

                        <div class="mt-0.5 text-sm text-slate-500">
                            {{ $year->start_date->format('d.m.Y') }} – {{ $year->end_date->format('d.m.Y') }}
                            · {{ $year->entries_count }} Buchung(en)
                        </div>

                        @if($year->isClosed())
                            <div class="mt-1 text-xs text-slate-500">
                                Abgeschlossen am {{ $year->closed_at->format('d.m.Y') }}
                                @if($year->general_meeting_date) nach JHV vom {{ $year->general_meeting_date->format('d.m.Y') }} @endif
                                @if($year->closedByUser) durch {{ $year->closedByUser->member?->full_name ?? $year->closedByUser->username }} @endif
                            </div>
                            @if($year->closing_note)
                                <div class="mt-1 text-xs italic text-slate-500">„{{ $year->closing_note }}“</div>
                            @endif
                        @endif
                    </div>

                    <div class="grid w-full grid-cols-2 gap-2 sm:flex sm:w-auto">
                        <a
                            href="{{ route('cash-book.index', ['jahr' => $year->cashBookYearID]) }}"
                            wire:navigate
                            class="rounded-lg border border-slate-300 px-3 py-2 text-center text-sm font-medium text-slate-700 hover:bg-slate-50"
                        >
                            Buchungen
                        </a>

                        <a
                            href="{{ route('cash-book.report', $year->cashBookYearID) }}"
                            target="_blank"
                            class="rounded-lg border border-slate-300 px-3 py-2 text-center text-sm font-medium text-slate-700 hover:bg-slate-50"
                        >
                            PDF-Bericht
                        </a>

                        @unless($year->isClosed())
                            <button
                                type="button"
                                wire:click="openEdit({{ $year->cashBookYearID }})"
                                class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
                            >
                                Bearbeiten
                            </button>

                            <button
                                type="button"
                                wire:click="openClose({{ $year->cashBookYearID }})"
                                class="rounded-lg bg-slate-900 px-3 py-2 text-sm font-medium text-white hover:bg-slate-800"
                            >
                                Abschließen
                            </button>
                        @endunless
                    </div>
                </div>

                <dl class="mt-4 grid grid-cols-2 gap-3 text-sm md:grid-cols-4">
                    <div class="rounded-lg bg-slate-50 p-3">
                        <dt class="text-xs text-slate-500">Anfangsbestand</dt>
                        <dd class="font-semibold text-slate-900">{{ $money($year->opening_balance) }}</dd>
                    </div>
                    <div class="rounded-lg bg-slate-50 p-3">
                        <dt class="text-xs text-slate-500">Einnahmen</dt>
                        <dd class="font-semibold text-emerald-700">{{ $money($year->summary['income']) }}</dd>
                    </div>
                    <div class="rounded-lg bg-slate-50 p-3">
                        <dt class="text-xs text-slate-500">Ausgaben</dt>
                        <dd class="font-semibold text-rose-700">{{ $money($year->summary['expense']) }}</dd>
                    </div>
                    <div class="rounded-lg bg-slate-50 p-3">
                        <dt class="text-xs text-slate-500">{{ $year->isClosed() ? 'Endbestand' : 'Aktueller Stand' }}</dt>
                        <dd class="font-semibold text-slate-900">{{ $money($year->closing_balance ?? $year->summary['balance']) }}</dd>
                    </div>
                </dl>
            </section>
        @empty
            <section class="rounded-xl border border-dashed border-slate-300 bg-white p-8 text-center text-sm text-slate-500">
                Noch keine Vereinsjahre angelegt. Lege das erste Jahr mit dem Kassastand zu Beginn an.
            </section>
        @endforelse
    </div>

    {{-- Vereinsjahr anlegen / bearbeiten --}}
    @if($showYearDialog)
        <div class="fixed inset-0 z-50 flex items-end justify-center bg-black/40 sm:items-center sm:p-4">
            <form wire:submit="saveYear" class="w-full rounded-t-2xl bg-white p-5 shadow-xl sm:max-w-md sm:rounded-xl sm:p-6">
                <h3 class="text-lg font-semibold text-slate-900">
                    {{ $editingYearId ? 'Vereinsjahr bearbeiten' : 'Neues Vereinsjahr' }}
                </h3>

                <div class="mt-4 space-y-4">
                    <div>
                        <label for="year-name" class="mb-1 block text-sm font-medium text-slate-700">Bezeichnung</label>
                        <input id="year-name" type="text" wire:model="name" placeholder="z.B. 2025/26"
                               class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-base sm:py-2 sm:text-sm">
                        @error('name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label for="year-start" class="mb-1 block text-sm font-medium text-slate-700">Beginn</label>
                            <input id="year-start" type="date" wire:model="startDate"
                                   class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-base sm:py-2 sm:text-sm">
                        </div>
                        <div>
                            <label for="year-end" class="mb-1 block text-sm font-medium text-slate-700">Ende (voraussichtl.)</label>
                            <input id="year-end" type="date" wire:model="endDate"
                                   class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-base sm:py-2 sm:text-sm">
                        </div>
                    </div>
                    @error('startDate') <p class="-mt-2 text-xs text-red-600">{{ $message }}</p> @enderror
                    @error('endDate') <p class="-mt-2 text-xs text-red-600">{{ $message }}</p> @enderror

                    <div>
                        <label for="year-opening" class="mb-1 block text-sm font-medium text-slate-700">Anfangsbestand (€)</label>
                        <input id="year-opening" type="text" inputmode="decimal" wire:model="openingBalance"
                               class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-right text-base sm:py-2 sm:text-sm">
                        @error('openingBalance') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        <p class="mt-1 text-xs text-slate-500">
                            Wird beim Abschluss des Vorjahres automatisch übernommen.
                        </p>
                    </div>
                </div>

                <div class="mt-6 flex items-center gap-2">
                    @if($editingYearId)
                        <button type="button"
                                wire:click="deleteYear({{ $editingYearId }})"
                                wire:confirm="Vereinsjahr wirklich löschen? Nur möglich, solange keine Buchungen vorhanden sind."
                                class="rounded-lg px-3 py-2 text-sm font-medium text-red-600 hover:bg-red-50">
                            Löschen
                        </button>
                    @endif

                    <button type="button" wire:click="closeYearDialog"
                            class="ml-auto rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                        Abbrechen
                    </button>
                    <button type="submit"
                            class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">
                        Speichern
                    </button>
                </div>
            </form>
        </div>
    @endif

    {{-- Jahresabschluss --}}
    @if($closingYear)
        <div class="fixed inset-0 z-50 flex items-end justify-center bg-black/40 sm:items-center sm:p-4">
            <form wire:submit="closeYear" class="w-full rounded-t-2xl bg-white p-5 shadow-xl sm:max-w-md sm:rounded-xl sm:p-6">
                <h3 class="text-lg font-semibold text-slate-900">
                    Vereinsjahr {{ $closingYear->name }} abschließen
                </h3>

                <dl class="mt-4 space-y-1 rounded-lg bg-slate-50 p-3 text-sm">
                    <div class="flex justify-between"><dt class="text-slate-500">Anfangsbestand</dt><dd>{{ $money($closingYear->opening_balance) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">+ Einnahmen</dt><dd class="text-emerald-700">{{ $money($closingYear->summary['income']) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">− Ausgaben</dt><dd class="text-rose-700">{{ $money($closingYear->summary['expense']) }}</dd></div>
                    <div class="flex justify-between border-t border-slate-200 pt-1 font-semibold"><dt>Endbestand</dt><dd>{{ $money($closingYear->summary['balance']) }}</dd></div>
                </dl>

                @if($closingYear->entries_without_receipt_count > 0)
                    <div class="mt-3 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800">
                        ⚠ {{ $closingYear->entries_without_receipt_count }} Buchung(en) ohne Beleg.
                    </div>
                @endif

                <div class="mt-4 space-y-4">
                    <div>
                        <label for="close-jhv" class="mb-1 block text-sm font-medium text-slate-700">Datum der Jahreshauptversammlung</label>
                        <input id="close-jhv" type="date" wire:model="generalMeetingDate"
                               class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-base sm:py-2 sm:text-sm">
                        @error('generalMeetingDate') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="close-note" class="mb-1 block text-sm font-medium text-slate-700">
                            Anmerkung <span class="font-normal text-slate-400">(optional, z.B. Entlastung durch Kassaprüfer)</span>
                        </label>
                        <textarea id="close-note" wire:model="closingNote" rows="2"
                                  class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-base sm:py-2 sm:text-sm"></textarea>
                        @error('closingNote') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <p class="text-xs text-slate-500">
                        Nach dem Abschluss können die Buchungen dieses Jahres nicht mehr geändert werden.
                        Der Endbestand wird als Anfangsbestand ins Folgejahr übernommen.
                    </p>
                </div>

                <div class="mt-6 flex justify-end gap-2">
                    <button type="button" wire:click="cancelClose"
                            class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                        Abbrechen
                    </button>
                    <button type="submit"
                            wire:confirm="Vereinsjahr {{ $closingYear->name }} jetzt endgültig abschließen?"
                            class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">
                        Endgültig abschließen
                    </button>
                </div>
            </form>
        </div>
    @endif

</div>
