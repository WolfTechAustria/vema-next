<div class="space-y-6">

    @include('partials.flash-messages')

    @include('partials.admin-settings-nav')

    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">

        <div>
            <h2 class="text-2xl font-bold tracking-tight">
                Mitgliederfelder
            </h2>

            <p class="mt-1 text-sm text-slate-500">
                Eigene Angaben zu euren Mitgliedern, z. B. Instrument, Verbandsnummer oder Trachtenträger. Sie erscheinen beim Mitglied unter
                „Weitere Angaben“, als Spalte und Filter in der Mitgliederliste, im Excel-Export und als Platzhalter in Vorlagen.
            </p>
        </div>

        @unless($showForm)
            <button
                type="button"
                wire:click="newField"
                class="shrink-0 rounded-lg bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white"
            >
                + Neues Feld
            </button>
        @endunless

    </div>

    @if($showForm)

        <section class="max-w-2xl rounded-xl border border-slate-200 bg-white p-6 shadow-sm">

            <h3 class="mb-4 font-semibold text-slate-900">
                {{ $editingFieldID ? 'Feld bearbeiten' : 'Neues Feld' }}
            </h3>

            <div class="space-y-4">

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">
                        Bezeichnung
                    </label>

                    <input
                        type="text"
                        wire:model="name"
                        placeholder="z. B. Instrument, Verbandsnummer, Trachtenträger ..."
                        class="w-full rounded-lg border border-slate-300 px-3 py-2"
                    >

                    @error('name')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">
                            Art
                        </label>

                        <select
                            wire:model.live="type"
                            @disabled($typeLocked)
                            class="w-full rounded-lg border border-slate-300 px-3 py-2 disabled:bg-slate-50"
                        >
                            @foreach($types as $typeOption)
                                <option value="{{ $typeOption->value }}">{{ $typeOption->label() }}</option>
                            @endforeach
                        </select>

                        @if($typeLocked)
                            <p class="mt-1 text-xs text-slate-500">Nicht mehr änderbar, weil bereits Werte erfasst sind.</p>
                        @endif

                        @error('type')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">
                            Mitgliederportal
                        </label>

                        <select wire:model="portal" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                            @foreach($portalOptions as $portalOption)
                                <option value="{{ $portalOption->value }}">{{ $portalOption->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <label class="flex items-center gap-3 text-sm text-slate-700">
                    <input type="checkbox" wire:model="onApplication" class="rounded border-slate-300">
                    Auch in der Online-Anmeldung abfragen
                </label>

                @if(\App\Enums\MemberFieldType::tryFrom($type)?->hasOptions())
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">
                            Auswahlmöglichkeiten
                        </label>

                        <textarea
                            wire:model="options"
                            rows="5"
                            placeholder="Eine pro Zeile, z. B.&#10;Flöte&#10;Klarinette&#10;Trompete"
                            class="w-full rounded-lg border border-slate-300 px-3 py-2"
                        ></textarea>

                        @error('options')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                @endif

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
                    wire:click="saveField"
                    class="rounded-lg bg-slate-900 px-4 py-2 font-semibold text-white"
                >
                    {{ $editingFieldID ? 'Änderungen speichern' : 'Feld anlegen' }}
                </button>
            </div>

        </section>

    @endif

    <section class="rounded-xl border border-slate-200 bg-white shadow-sm">

        <div class="divide-y divide-slate-100">

            @forelse($fields as $field)

                <div wire:key="member-field-{{ $field->fieldID }}" class="flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between sm:gap-4">

                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="font-medium text-slate-900">
                                {{ $field->name }}
                            </span>

                            <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600">
                                {{ $field->type->label() }}
                            </span>

                            @unless($field->active)
                                <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-500">
                                    inaktiv
                                </span>
                            @endunless

                            @if($field->on_application)
                                <span class="rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700">
                                    Online-Anmeldung
                                </span>
                            @endif

                            @if($field->portal !== \App\Enums\MemberFieldPortal::Hidden)
                                <span class="rounded-full bg-sky-50 px-2 py-0.5 text-xs font-medium text-sky-700">
                                    {{ $field->portal->label() }}
                                </span>
                            @endif
                        </div>

                        <div class="mt-1 text-sm text-slate-500">
                            {{ $field->values_count }} {{ $field->values_count === 1 ? 'Wert' : 'Werte' }} erfasst
                            · Platzhalter <code class="rounded bg-slate-100 px-1 text-xs">{{ $field->placeholder() }}</code>
                            @if($field->type->hasOptions())
                                · {{ implode(', ', $field->options ?? []) }}
                            @endif
                        </div>
                    </div>

                    <div class="flex shrink-0 flex-wrap items-center gap-x-4 gap-y-2 sm:gap-2">

                        <button type="button" wire:click="moveUp({{ $field->fieldID }})" class="px-1 text-lg text-slate-400 hover:text-slate-700 sm:px-0 sm:text-base" title="Nach oben">
                            ↑
                        </button>

                        <button type="button" wire:click="moveDown({{ $field->fieldID }})" class="px-1 text-lg text-slate-400 hover:text-slate-700 sm:px-0 sm:text-base" title="Nach unten">
                            ↓
                        </button>

                        <button type="button" wire:click="toggleActive({{ $field->fieldID }})" class="text-sm font-medium text-slate-600 hover:text-slate-900">
                            {{ $field->active ? 'Deaktivieren' : 'Aktivieren' }}
                        </button>

                        <button type="button" wire:click="editField({{ $field->fieldID }})" class="text-sm font-medium text-slate-600 hover:text-slate-900">
                            Bearbeiten
                        </button>

                        <button
                            type="button"
                            wire:click="deleteField({{ $field->fieldID }})"
                            wire:confirm="Feld „{{ $field->name }}“ löschen? {{ $field->values_count }} erfasste Werte gehen verloren. Zum Ausblenden stattdessen deaktivieren."
                            class="text-sm font-medium text-red-600 hover:text-red-800"
                        >
                            Löschen
                        </button>

                    </div>

                </div>

            @empty

                <div class="px-6 py-8 text-center text-sm text-slate-500">
                    Noch keine eigenen Mitgliederfelder angelegt.
                </div>

            @endforelse

        </div>

    </section>

</div>
