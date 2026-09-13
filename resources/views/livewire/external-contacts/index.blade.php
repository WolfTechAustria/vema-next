<div class="space-y-6">

    <div>
        <h2 class="text-2xl font-bold tracking-tight">
            Externe Kontakte
        </h2>

        <p class="mt-1 text-sm text-slate-500">
            Externe Personen für Rundschreiben, Empfängergruppen und später den Dienstplan verwalten.
        </p>
    </div>


    @if(session('success'))
        <div class="rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
            {{ session('success') }}
        </div>
    @endif


    <div class="grid gap-6 xl:grid-cols-[420px_1fr]">

        {{-- Formular --}}
        <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">

            <div class="flex items-center justify-between">

                <h3 class="text-lg font-semibold">
                    {{ $editingContactID ? 'Kontakt bearbeiten' : 'Neuer Kontakt' }}
                </h3>

                @if($editingContactID)
                    <button
                        type="button"
                        wire:click="cancelEdit"
                        class="text-sm font-medium text-slate-500 hover:text-slate-800"
                    >
                        Abbrechen
                    </button>
                @endif

            </div>


            <div class="mt-5 space-y-4">

                <div class="grid grid-cols-2 gap-3">

                    <div>
                        <label class="mb-1 block text-sm font-medium">
                            Vorname
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
                            Nachname
                        </label>

                        <input
                            type="text"
                            wire:model="surname"
                            class="w-full rounded-lg border border-slate-300 px-3 py-2"
                        >

                        @error('surname')
                        <p class="mt-1 text-xs text-red-600">
                            {{ $message }}
                        </p>
                        @enderror
                    </div>

                </div>


                <div>
                    <label class="mb-1 block text-sm font-medium">
                        Organisation / Firma
                    </label>

                    <input
                        type="text"
                        wire:model="organization"
                        class="w-full rounded-lg border border-slate-300 px-3 py-2"
                    >
                </div>


                <div>
                    <label class="mb-1 block text-sm font-medium">
                        E-Mail
                    </label>

                    <input
                        type="email"
                        wire:model="email"
                        class="w-full rounded-lg border border-slate-300 px-3 py-2"
                    >

                    @error('email')
                    <p class="mt-1 text-xs text-red-600">
                        {{ $message }}
                    </p>
                    @enderror
                </div>


                <div>
                    <label class="mb-1 block text-sm font-medium">
                        Telefon
                    </label>

                    <input
                        type="text"
                        wire:model="phone"
                        class="w-full rounded-lg border border-slate-300 px-3 py-2"
                    >
                </div>


                <div>
                    <label class="mb-1 block text-sm font-medium">
                        Notiz
                    </label>

                    <textarea
                        wire:model="note"
                        rows="3"
                        class="w-full rounded-lg border border-slate-300 px-3 py-2"
                    ></textarea>
                </div>


                <label class="flex items-center gap-3">

                    <input
                        type="checkbox"
                        wire:model="active"
                        class="h-4 w-4 rounded border-slate-300"
                    >

                    <span class="text-sm font-medium text-slate-700">
                        Kontakt aktiv
                    </span>

                </label>


                <button
                    type="button"
                    wire:click="save"
                    wire:loading.attr="disabled"
                    wire:target="save"
                    class="w-full rounded-lg bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white disabled:opacity-60"
                >
                    <span wire:loading.remove wire:target="save">
                        {{ $editingContactID ? 'Änderungen speichern' : 'Kontakt anlegen' }}
                    </span>

                    <span wire:loading wire:target="save">
                        Speichern …
                    </span>
                </button>

            </div>

        </section>


        {{-- Liste --}}
        <section class="rounded-xl border border-slate-200 bg-white shadow-sm">

            <div class="border-b border-slate-200 p-4">

                <input
                    type="search"
                    wire:model.live.debounce.300ms="search"
                    placeholder="Name, Firma, E-Mail oder Telefon suchen …"
                    class="w-full rounded-lg border border-slate-300 px-3 py-2"
                >

            </div>


            <div class="divide-y divide-slate-100">

                @forelse($contacts as $contact)

                    <div
                        wire:key="external-contact-{{ $contact->externalContactID }}"
                        class="flex items-start justify-between gap-4 px-6 py-4"
                    >

                        <div class="min-w-0">

                            <div class="flex flex-wrap items-center gap-2">

                                <div class="font-medium text-slate-900">
                                    {{ $contact->surname }}
                                    {{ $contact->name }}
                                </div>

                                @if($contact->active)
                                    <span class="rounded-full bg-green-50 px-2 py-0.5 text-xs font-medium text-green-700">
                                        Aktiv
                                    </span>
                                @else
                                    <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-500">
                                        Inaktiv
                                    </span>
                                @endif

                            </div>


                            @if($contact->organization)
                                <div class="mt-1 text-sm text-slate-500">
                                    {{ $contact->organization }}
                                </div>
                            @endif


                            <div class="mt-2 space-y-1 text-sm text-slate-600">

                                <div>
                                    {{ $contact->email }}
                                </div>

                                @if($contact->phone)
                                    <div>
                                        {{ $contact->phone }}
                                    </div>
                                @endif

                            </div>

                        </div>


                        <div class="flex shrink-0 items-center gap-3">

                            <button
                                type="button"
                                wire:click="edit({{ $contact->externalContactID }})"
                                class="text-sm font-medium text-slate-600 hover:text-slate-900"
                            >
                                Bearbeiten
                            </button>


                            <button
                                type="button"
                                wire:click="toggleActive({{ $contact->externalContactID }})"
                                class="text-sm font-medium text-slate-600 hover:text-slate-900"
                            >
                                {{ $contact->active ? 'Deaktivieren' : 'Aktivieren' }}
                            </button>


                            <button
                                type="button"
                                wire:click="delete({{ $contact->externalContactID }})"
                                wire:confirm="Externen Kontakt wirklich löschen?"
                                class="text-sm font-medium text-red-600 hover:text-red-800"
                            >
                                Löschen
                            </button>

                        </div>

                    </div>

                @empty

                    <div class="px-6 py-10 text-center text-sm text-slate-500">
                        Keine externen Kontakte gefunden.
                    </div>

                @endforelse

            </div>

        </section>

    </div>

</div>
