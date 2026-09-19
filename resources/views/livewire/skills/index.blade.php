<div class="space-y-6">

    <div>
        <h2 class="text-2xl font-bold tracking-tight">
            Fähigkeiten
        </h2>

        <p class="mt-1 text-sm text-slate-500">
            Fähigkeiten-Tags, die Mitgliedern und externen Helfern zugeordnet werden können
            und als Voraussetzung für Dienstbezeichnungen im Dienstplan dienen (z. B. "Auswertungsprogramm").
        </p>
    </div>

    @include('partials.flash-messages')

    <div class="grid gap-6 lg:grid-cols-2">

        <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">

            <div class="flex items-center justify-between">

                <h3 class="text-lg font-semibold">
                    {{ $editingSkillID ? 'Fähigkeit bearbeiten' : 'Neue Fähigkeit' }}
                </h3>

                @if($editingSkillID)
                    <button
                        type="button"
                        wire:click="cancelEdit"
                        class="text-sm font-medium text-slate-500 hover:text-slate-800"
                    >
                        Abbrechen
                    </button>
                @endif

            </div>

            <div class="mt-4 space-y-4">

                <div>
                    <label class="mb-1 block text-sm font-medium">
                        Name
                    </label>

                    <input
                        type="text"
                        wire:model="name"
                        class="w-full rounded-lg border border-slate-300 px-3 py-2"
                    >

                    @error('name')
                    <p class="mt-1 text-xs text-red-600">
                        {{ $message }}
                    </p>
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
                    <p class="mt-1 text-xs text-red-600">
                        {{ $message }}
                    </p>
                    @enderror
                </div>

                <label class="flex items-center gap-3">
                    <input
                        type="checkbox"
                        wire:model="active"
                        class="rounded border-slate-300"
                    >

                    <span class="text-sm font-medium text-slate-700">
                        Aktiv
                    </span>
                </label>

                <div class="flex items-center gap-3">

                    @if($editingSkillID)

                        <button
                            type="button"
                            wire:click="updateSkill"
                            wire:loading.attr="disabled"
                            wire:target="updateSkill"
                            class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white disabled:opacity-60"
                        >
                            <span wire:loading.remove wire:target="updateSkill">
                                Änderungen speichern
                            </span>

                            <span wire:loading wire:target="updateSkill">
                                Speichern …
                            </span>
                        </button>

                    @else

                        <button
                            type="button"
                            wire:click="createSkill"
                            wire:loading.attr="disabled"
                            wire:target="createSkill"
                            class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white disabled:opacity-60"
                        >
                            <span wire:loading.remove wire:target="createSkill">
                                Fähigkeit anlegen
                            </span>

                            <span wire:loading wire:target="createSkill">
                                Anlegen …
                            </span>
                        </button>

                    @endif

                </div>

            </div>

        </section>

        <section class="rounded-xl border border-slate-200 bg-white shadow-sm">

            <div class="border-b border-slate-200 px-6 py-4">
                <h3 class="font-semibold">
                    Bestehende Fähigkeiten
                </h3>
            </div>

            <div class="divide-y divide-slate-100">

                @forelse($skills as $skill)

                    <div
                        wire:key="skill-{{ $skill->skillID }}"
                        class="flex items-start justify-between gap-4 px-6 py-4"
                    >

                        <div>
                            <div class="font-medium text-slate-900">
                                {{ $skill->name }}

                                @unless($skill->active)
                                    <span class="ml-2 rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-500">
                                        inaktiv
                                    </span>
                                @endunless
                            </div>

                            @if($skill->description)
                                <div class="mt-1 text-sm text-slate-500">
                                    {{ $skill->description }}
                                </div>
                            @endif

                            <div class="mt-2 flex flex-wrap gap-3 text-xs text-slate-500">
                                <span>
                                    {{ $skill->members_count }}
                                    Mitglieder
                                </span>

                                <span>
                                    {{ $skill->external_contacts_count }}
                                    externe Kontakte
                                </span>
                            </div>
                        </div>

                        <div class="flex items-center gap-3">

                            <button
                                type="button"
                                wire:click="editSkill({{ $skill->skillID }})"
                                class="text-sm font-medium text-slate-600 hover:text-slate-900"
                            >
                                Bearbeiten
                            </button>

                            <button
                                type="button"
                                wire:click="deleteSkill({{ $skill->skillID }})"
                                wire:confirm="Fähigkeit wirklich löschen?"
                                class="text-sm font-medium text-red-600 hover:text-red-800"
                            >
                                Löschen
                            </button>

                        </div>

                    </div>

                @empty

                    <div class="px-6 py-8 text-center text-sm text-slate-500">
                        Noch keine Fähigkeiten vorhanden.
                    </div>

                @endforelse

            </div>

        </section>

    </div>

</div>
