<div class="space-y-6">

    @include('partials.flash-messages')

    @include('partials.admin-settings-nav')

    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">

        <div>
            <h2 class="text-2xl font-bold tracking-tight">
                Vorstandsfunktionen
            </h2>

            <p class="mt-1 text-sm text-slate-500">
                Bezeichnungen und Reihenfolge der Funktionen, die Mitgliedern zugewiesen werden können (z. B. Obmann statt Oberschützenmeister).
            </p>
        </div>

        @unless($showForm)
            <button
                type="button"
                wire:click="newFunction"
                class="shrink-0 rounded-lg bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white"
            >
                + Neue Funktion
            </button>
        @endunless

    </div>

    @if($showForm)

        <section class="max-w-2xl rounded-xl border border-slate-200 bg-white p-6 shadow-sm">

            <h3 class="mb-4 font-semibold text-slate-900">
                {{ $editingFunctionID ? 'Funktion bearbeiten' : 'Neue Funktion' }}
            </h3>

            <div class="space-y-4">

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">
                        Bezeichnung
                    </label>

                    <input
                        type="text"
                        wire:model="name"
                        placeholder="z. B. Obmann, Obmann-Stv., Beirat ..."
                        class="w-full rounded-lg border border-slate-300 px-3 py-2"
                    >

                    @error('name')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror

                    @if($editingFunctionID)
                        <p class="mt-1 text-xs text-slate-500">
                            Eine Umbenennung gilt auch für bisherige und frühere Zuweisungen.
                        </p>
                    @endif
                </div>

                <label class="flex items-start gap-3">
                    <input type="checkbox" wire:model="is_top_vorstand" class="mt-0.5 rounded border-slate-300">

                    <span class="text-sm text-slate-700">
                        <span class="font-medium">Spitzenfunktion</span>
                        <span class="block text-xs text-slate-500">
                            z. B. Obmann, Oberschützenmeister, Stellvertreter. Wer eine Spitzenfunktion hat, erhält keine automatische Rats-Funktion.
                        </span>
                    </span>
                </label>

                <label class="flex items-start gap-3">
                    <input type="checkbox" wire:model="implies_schuetzenrat" class="mt-0.5 rounded border-slate-300">

                    <span class="text-sm text-slate-700">
                        <span class="font-medium">
                            Erhält automatisch {{ $councilName ? '„'.$councilName.'“' : 'die Rats-Funktion' }}
                        </span>
                        <span class="block text-xs text-slate-500">
                            z. B. Schriftführer und Kassier, die zugleich dem Rat/Ausschuss angehören.
                        </span>
                    </span>
                </label>

                @error('implies_schuetzenrat')
                    <p class="text-xs text-red-600">{{ $message }}</p>
                @enderror

                <label class="flex items-start gap-3">
                    <input type="checkbox" wire:model="is_council" class="mt-0.5 rounded border-slate-300">

                    <span class="text-sm text-slate-700">
                        <span class="font-medium">Ist die Rats-Funktion</span>
                        <span class="block text-xs text-slate-500">
                            z. B. Schützenrat, Ausschuss, Beirat. Kann nur eine Funktion sein.
                        </span>
                    </span>
                </label>

            </div>

            <div class="mt-5 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                <button
                    type="button"
                    wire:click="cancelEdit"
                    class="rounded-lg border border-slate-300 px-4 py-2"
                >
                    Abbrechen
                </button>

                <button
                    type="button"
                    wire:click="saveFunction"
                    class="rounded-lg bg-slate-900 px-4 py-2 font-semibold text-white"
                >
                    {{ $editingFunctionID ? 'Änderungen speichern' : 'Funktion anlegen' }}
                </button>
            </div>

        </section>

    @endif

    <section class="rounded-xl border border-slate-200 bg-white shadow-sm">

        <div class="divide-y divide-slate-100">

            @forelse($functions as $function)

                <div wire:key="board-function-{{ $function->boardFunctionID }}" class="flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between sm:gap-4">

                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="font-medium text-slate-900">
                                {{ $function->name }}
                            </span>

                            @unless($function->active)
                                <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-500">
                                    inaktiv
                                </span>
                            @endunless

                            @if($function->is_top_vorstand)
                                <span class="rounded-full bg-slate-900 px-2 py-0.5 text-xs font-medium text-white">
                                    Spitzenfunktion
                                </span>
                            @endif

                            @if($function->is_council)
                                <span class="rounded-full bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-700">
                                    Rats-Funktion
                                </span>
                            @endif

                            @if($function->implies_schuetzenrat && $councilName)
                                <span class="rounded-full bg-sky-50 px-2 py-0.5 text-xs font-medium text-sky-700">
                                    + {{ $councilName }}
                                </span>
                            @endif
                        </div>

                        <div class="mt-1 text-sm text-slate-500">
                            {{ $function->current_holders_count }} {{ $function->current_holders_count === 1 ? 'Mitglied' : 'Mitglieder' }} aktuell
                        </div>
                    </div>

                    <div class="flex shrink-0 flex-wrap items-center gap-x-4 gap-y-2 sm:gap-2">

                        <button type="button" wire:click="moveUp({{ $function->boardFunctionID }})" class="px-1 text-lg text-slate-400 hover:text-slate-700 sm:px-0 sm:text-base" title="Nach oben">
                            ↑
                        </button>

                        <button type="button" wire:click="moveDown({{ $function->boardFunctionID }})" class="px-1 text-lg text-slate-400 hover:text-slate-700 sm:px-0 sm:text-base" title="Nach unten">
                            ↓
                        </button>

                        <button type="button" wire:click="toggleActive({{ $function->boardFunctionID }})" class="text-sm font-medium text-slate-600 hover:text-slate-900">
                            {{ $function->active ? 'Deaktivieren' : 'Aktivieren' }}
                        </button>

                        <button type="button" wire:click="editFunction({{ $function->boardFunctionID }})" class="text-sm font-medium text-slate-600 hover:text-slate-900">
                            Bearbeiten
                        </button>

                        <button type="button" wire:click="deleteFunction({{ $function->boardFunctionID }})" wire:confirm="Funktion wirklich löschen?" class="text-sm font-medium text-red-600 hover:text-red-800">
                            Löschen
                        </button>

                    </div>

                </div>

            @empty

                <div class="px-6 py-8 text-center text-sm text-slate-500">
                    Noch keine Vorstandsfunktionen angelegt.
                </div>

            @endforelse

        </div>

    </section>

</div>
