<div class="space-y-6">

    @include('partials.flash-messages')

    {{-- Kopf --}}
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h2 class="text-2xl font-bold tracking-tight">SEPA-Lastschrift {{ $year->name ?: $year->year }}</h2>
            <p class="mt-1 text-sm text-slate-500">
                Offene Beiträge von Mitgliedern mit Lastschriftmandat als Datei für das Online-Banking.
            </p>
        </div>

        <a href="{{ route('membership-fees.index') }}" wire:navigate class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
            Zurück zu den Beiträgen
        </a>
    </div>

    @if($missingSetup !== [])
        <div class="rounded-xl border border-amber-200 bg-amber-50 px-6 py-4 text-sm text-amber-900">
            Für Lastschriften fehlt noch: <strong>{{ implode(', ', $missingSetup) }}</strong>.
            Bitte unter <a href="{{ route('admin.settings.sepa') }}" class="underline">Einstellungen → SEPA-Lastschrift</a> bzw. Allgemein (IBAN) ergänzen.
            Die Gläubiger-ID erhalten Sie von Ihrer Hausbank.
        </div>
    @endif

    {{-- Neuer Lauf --}}
    <section class="rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 px-6 py-4">
            <div>
                <h3 class="font-semibold text-slate-900">Neuer Einzug</h3>
                <p class="mt-1 text-sm text-slate-500">
                    {{ $candidates->count() }} offene {{ $candidates->count() === 1 ? 'Beitrag' : 'Beiträge' }} mit Mandat.
                </p>
            </div>
            @if($candidates->isNotEmpty())
                <div class="flex gap-3 text-sm">
                    <button type="button" wire:click="selectAll" class="text-slate-600 hover:text-slate-900">Alle</button>
                    <button type="button" wire:click="selectNone" class="text-slate-600 hover:text-slate-900">Keine</button>
                </div>
            @endif
        </div>

        @error('sepa')
            <p class="border-b border-red-100 bg-red-50 px-6 py-3 text-sm text-red-700">{{ $message }}</p>
        @enderror

        @if($candidates->isEmpty())
            <p class="px-6 py-4 text-sm text-slate-500">Keine offenen Beiträge mit Lastschriftmandat.</p>
        @else
            <div class="max-h-[28rem] divide-y divide-slate-100 overflow-y-auto">
                @foreach($candidates as $entry)
                    @php($mandate = $entry->member->activeSepaMandate)
                    <label wire:key="sepa-candidate-{{ $entry->entryID }}" class="flex cursor-pointer items-center gap-3 px-6 py-3 hover:bg-slate-50">
                        <input type="checkbox" wire:model.live="selected" value="{{ $entry->entryID }}" class="rounded border-slate-300">
                        <span class="min-w-0 flex-1">
                            <span class="block text-sm font-medium text-slate-900">{{ $entry->member->surname }} {{ $entry->member->name }}</span>
                            <span class="block text-xs text-slate-500">
                                {{ $mandate->maskedIban() }} · {{ $mandate->reference }} · {{ $mandate->sequenceType() === 'FRST' ? 'Erstlastschrift' : 'Folgelastschrift' }}
                            </span>
                        </span>
                        <span class="text-sm font-semibold text-slate-900">{{ number_format((float) $entry->amount, 2, ',', '.') }} €</span>
                    </label>
                @endforeach
            </div>

            <form wire:submit="createRun" class="flex flex-col gap-4 border-t border-slate-200 bg-slate-50 px-6 py-4 lg:flex-row lg:items-end">
                <div>
                    <label for="sepa-date" class="mb-1 block text-sm font-medium text-slate-700">Einzugsdatum</label>
                    <input id="sepa-date" type="date" wire:model="collectionDate" min="{{ $earliestDate->toDateString() }}" class="rounded-lg border-slate-300 text-sm">
                    @error('collectionDate') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <label class="flex items-center gap-2 text-sm text-slate-700 lg:pb-2">
                    <input type="checkbox" wire:model="notifyMembers" class="rounded border-slate-300">
                    Mitglieder per E-Mail vorab informieren
                </label>

                <div class="flex flex-1 items-center justify-end gap-4">
                    <span class="text-sm text-slate-600">
                        {{ count($selected) }} ausgewählt · <strong>{{ number_format($selectedTotal, 2, ',', '.') }} €</strong>
                    </span>
                    <button type="submit" @disabled($missingSetup !== [] || $selected === []) class="rounded-lg bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-800 disabled:opacity-50">
                        Lastschrift-Datei erstellen
                    </button>
                </div>
            </form>
        @endif
    </section>

    {{-- Läufe --}}
    @if($runs->isNotEmpty())
        <section class="rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-6 py-4">
                <h3 class="font-semibold text-slate-900">Lastschrift-Läufe</h3>
                <p class="mt-1 text-sm text-slate-500">
                    Datei herunterladen, im Online-Banking (z. B. ELBA, George) als SEPA-Lastschrift hochladen und nach der Gutschrift <strong>Einzug bestätigen</strong> – erst dann gelten die Beiträge als bezahlt.
                </p>
            </div>

            <div class="divide-y divide-slate-100">
                @foreach($runs as $run)
                    <div wire:key="sepa-run-{{ $run->runID }}" x-data="{ open: false }" class="px-6 py-4">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div class="text-sm">
                                <div class="font-medium text-slate-900">
                                    Einzug am {{ $run->collection_date->format('d.m.Y') }} · {{ $run->entry_count }} Beiträge · {{ number_format((float) $run->total_amount, 2, ',', '.') }} €
                                </div>
                                <div class="mt-0.5 flex flex-wrap items-center gap-2 text-xs text-slate-500">
                                    <span class="rounded-full px-2 py-0.5 font-medium {{ $run->statusClasses() }}">{{ $run->statusLabel() }}</span>
                                    erstellt {{ $run->created_at?->format('d.m.Y H:i') }}
                                    @if($run->confirmed_at) · bestätigt {{ $run->confirmed_at->format('d.m.Y') }} @endif
                                </div>
                            </div>

                            <div class="flex flex-wrap items-center gap-2">
                                <button type="button" x-on:click="open = ! open" class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm text-slate-700 hover:bg-slate-50" x-text="open ? 'Posten ausblenden' : 'Posten anzeigen'"></button>
                                @if($run->status !== 'cancelled')
                                    <a href="{{ route('membership-fees.sepa.download', $run) }}" class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50">XML herunterladen</a>
                                @endif
                                @if($run->isOpen())
                                    <button type="button" wire:click="confirmRun({{ $run->runID }})" wire:confirm="Ist der Betrag von {{ number_format((float) $run->total_amount, 2, ',', '.') }} € am Konto eingegangen? Die Beiträge werden als bezahlt markiert." class="rounded-lg bg-emerald-600 px-3 py-1.5 text-sm font-semibold text-white hover:bg-emerald-700">
                                        Einzug bestätigen
                                    </button>
                                    <button type="button" wire:click="cancelRun({{ $run->runID }})" wire:confirm="Lauf verwerfen? Nur wenn die Datei NICHT bei der Bank eingereicht wurde – die Beiträge werden wieder offen." class="text-sm text-red-600 hover:text-red-800">
                                        Verwerfen
                                    </button>
                                @endif
                            </div>
                        </div>

                        <div x-show="open" x-cloak class="mt-3 divide-y divide-slate-100 rounded-lg border border-slate-200">
                            @foreach($run->items as $item)
                                <div wire:key="sepa-item-{{ $item->itemID }}" class="flex flex-wrap items-center justify-between gap-3 px-4 py-2 text-sm">
                                    <span class="{{ $item->returned_at ? 'text-slate-400 line-through' : 'text-slate-700' }}">
                                        {{ $item->entry?->member?->full_name }} · {{ number_format((float) $item->amount, 2, ',', '.') }} € · {{ $item->sequence_type }}
                                    </span>
                                    @if($item->returned_at)
                                        <span class="rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-700">Rücklastschrift {{ $item->returned_at->format('d.m.Y') }}</span>
                                    @elseif($run->status === 'confirmed')
                                        <button type="button" wire:click="markReturned({{ $item->itemID }})" wire:confirm="Rücklastschrift für {{ $item->entry?->member?->full_name }} erfassen? Der Beitrag wird wieder offen." class="text-xs text-red-600 hover:text-red-800">
                                            Rücklastschrift
                                        </button>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    {{-- Ohne Mandat --}}
    @if($withoutMandate->isNotEmpty())
        <section class="rounded-xl border border-slate-200 bg-white shadow-sm" x-data="{ open: false }">
            <button type="button" x-on:click="open = ! open" class="flex w-full items-center justify-between px-6 py-4 text-left">
                <span>
                    <span class="font-semibold text-slate-900">Offen ohne Mandat</span>
                    <span class="ml-2 text-sm text-slate-500">{{ $withoutMandate->count() }} Beiträge, {{ number_format($withoutMandate->sum(fn ($entry) => (float) $entry->amount), 2, ',', '.') }} €</span>
                </span>
                <span class="text-slate-400" x-text="open ? '−' : '+'"></span>
            </button>
            <div x-show="open" x-cloak class="divide-y divide-slate-100 border-t border-slate-200">
                @foreach($withoutMandate as $entry)
                    <a wire:key="sepa-missing-{{ $entry->entryID }}" href="{{ route('members.show', $entry->memberID) }}" class="flex items-center justify-between px-6 py-2.5 text-sm hover:bg-slate-50">
                        <span class="text-slate-700">{{ $entry->member->surname }} {{ $entry->member->name }}</span>
                        <span class="text-slate-500">{{ number_format((float) $entry->amount, 2, ',', '.') }} €</span>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

</div>
