<div class="space-y-6">

    <div>
        <h2 class="text-2xl font-bold tracking-tight">
            Empfängergruppen
        </h2>

        <p class="mt-1 text-sm text-slate-500">
            Mitglieder zu wiederverwendbaren Gruppen zusammenfassen.
        </p>
    </div>

    @include('partials.flash-messages')

    <div class="grid gap-6 lg:grid-cols-2">

        <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">

            <div class="flex items-center justify-between">

                <h3 class="text-lg font-semibold">
                    {{ $editingGroupID ? 'Gruppe bearbeiten' : 'Neue Gruppe' }}
                </h3>

                @if($editingGroupID)
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

                <div>
                    <label class="mb-2 block text-sm font-medium">
                        Mitglieder
                    </label>

                    <div class="max-h-80 overflow-y-auto rounded-lg border border-slate-200">

                        @foreach($members as $member)
                            <label class="flex items-center gap-3 border-b border-slate-100 px-4 py-2 last:border-b-0">

                                <input
                                    type="checkbox"
                                    wire:model.live="selectedMembers"
                                    value="{{ $member->memberID }}"
                                    class="h-4 w-4 rounded border-slate-300"
                                >

                                <span class="text-sm text-slate-700">
                                    {{ $member->surname }}
                                    {{ $member->name }}
                                </span>

                            </label>
                        @endforeach

                    </div>
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium">
                        Externe Kontakte
                    </label>

                    <div class="max-h-80 overflow-y-auto rounded-lg border border-slate-200">

                        @forelse($externalContacts as $contact)

                            <label class="flex items-center gap-3 border-b border-slate-100 px-4 py-2 last:border-b-0">

                                <input
                                    type="checkbox"
                                    wire:model.live="selectedExternalContacts"
                                    value="{{ $contact->externalContactID }}"
                                    class="h-4 w-4 rounded border-slate-300"
                                >

                                <div class="min-w-0">

                                    <div class="text-sm text-slate-700">
                                        {{ $contact->surname }}
                                        {{ $contact->name }}
                                    </div>

                                    @if($contact->organization)
                                        <div class="text-xs text-slate-500">
                                            {{ $contact->organization }}
                                        </div>
                                    @endif

                                    <div class="text-xs text-slate-400">
                                        {{ $contact->email }}
                                    </div>

                                </div>

                            </label>

                        @empty

                            <div class="px-4 py-5 text-center text-sm text-slate-500">
                                Keine aktiven externen Kontakte vorhanden.
                            </div>

                        @endforelse

                    </div>
                </div>

                <div class="flex items-center gap-3">

                    @if($editingGroupID)

                        <button
                            type="button"
                            wire:click="updateGroup"
                            wire:loading.attr="disabled"
                            wire:target="updateGroup"
                            class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white disabled:opacity-60"
                        >
                            <span wire:loading.remove wire:target="updateGroup">
                                Änderungen speichern
                            </span>

                            <span wire:loading wire:target="updateGroup">
                                Speichern …
                            </span>
                        </button>

                    @else

                        <button
                            type="button"
                            wire:click="createGroup"
                            wire:loading.attr="disabled"
                            wire:target="createGroup"
                            class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white disabled:opacity-60"
                        >
                            <span wire:loading.remove wire:target="createGroup">
                                Gruppe anlegen
                            </span>

                            <span wire:loading wire:target="createGroup">
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
                    Bestehende Gruppen
                </h3>
            </div>

            <div class="divide-y divide-slate-100">

                @forelse($groups as $group)

                    <div
                        wire:key="recipient-group-{{ $group->groupID }}"
                        class="flex items-start justify-between gap-4 px-6 py-4"
                    >

                        <div>
                            <div class="font-medium text-slate-900">
                                {{ $group->name }}
                            </div>

                            @if($group->description)
                                <div class="mt-1 text-sm text-slate-500">
                                    {{ $group->description }}
                                </div>
                            @endif

                            <div class="mt-2 flex flex-wrap gap-3 text-xs text-slate-500">

                                <span>
                                    {{ $group->members->count() }}
                                    Mitglieder
                                </span>

                                                            <span>
                                    {{ $group->externalContacts->count() }}
                                    externe Kontakte
                                </span>

                            </div>
                        </div>

                        <div class="flex items-center gap-3">

                            <button
                                type="button"
                                wire:click="editGroup({{ $group->groupID }})"
                                class="text-sm font-medium text-slate-600 hover:text-slate-900"
                            >
                                Bearbeiten
                            </button>

                            <button
                                type="button"
                                wire:click="deleteGroup({{ $group->groupID }})"
                                wire:confirm="Empfängergruppe wirklich löschen?"
                                class="text-sm font-medium text-red-600 hover:text-red-800"
                            >
                                Löschen
                            </button>

                        </div>

                    </div>

                @empty

                    <div class="px-6 py-8 text-center text-sm text-slate-500">
                        Noch keine Empfängergruppen vorhanden.
                    </div>

                @endforelse

            </div>

        </section>

    </div>

</div>
