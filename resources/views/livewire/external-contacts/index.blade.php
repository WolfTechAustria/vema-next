<div class="space-y-6">

    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <h2 class="text-2xl font-bold tracking-tight">
                Externe Kontakte
            </h2>

            <p class="mt-1 text-sm text-slate-500">
                Externe Personen für Rundschreiben, Empfängergruppen und später den Dienstplan verwalten.
            </p>
        </div>

        @unless($showImport)
            <button
                type="button"
                wire:click="$set('showImport', true)"
                class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"
            >
                Kontakte importieren
            </button>
        @endunless
    </div>


    @include('partials.flash-messages')


    @if($showImport)
        <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">

            <div class="flex items-center justify-between">
                <h3 class="text-lg font-semibold">
                    Kontakte aus Excel / CSV importieren
                </h3>

                <button
                    type="button"
                    wire:click="cancelImport"
                    class="text-sm font-medium text-slate-500 hover:text-slate-800"
                >
                    Abbrechen
                </button>
            </div>

            <p class="mt-2 text-sm text-slate-500">
                Erkannt werden die Spalten Verein/Organisation, Name bzw. Obmann/Obfrau (Format „Nachname Vorname“)
                oder Vorname und Nachname, Adresse, E-Mail und Telefon. Adresse und Zusätze werden in die Notiz übernommen.
                Kontakte mit bereits vorhandener E-Mail-Adresse werden aktualisiert, Zeilen ohne E-Mail übersprungen.
            </p>

            <div class="mt-4">
                <input
                    type="file"
                    wire:model="importFile"
                    accept=".xlsx,.xls,.csv"
                    class="block w-full text-sm text-slate-600 file:mr-4 file:rounded-lg file:border-0 file:bg-slate-100 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-slate-700 hover:file:bg-slate-200"
                >

                <div wire:loading wire:target="importFile" class="mt-2 text-sm text-slate-500">
                    Datei wird gelesen …
                </div>

                @error('importFile')
                <p class="mt-1 text-xs text-red-600">
                    {{ $message }}
                </p>
                @enderror
            </div>

            @if($importPreview)
                @php
                    $importCounts = collect($importPreview)->countBy('status');
                @endphp

                <div class="mt-5 flex flex-wrap gap-2 text-sm">
                    <span class="rounded-full bg-green-50 px-3 py-1 font-medium text-green-700">
                        {{ $importCounts->get('create', 0) }} neu
                    </span>
                    <span class="rounded-full bg-blue-50 px-3 py-1 font-medium text-blue-700">
                        {{ $importCounts->get('update', 0) }} aktualisieren
                    </span>
                    <span class="rounded-full bg-slate-100 px-3 py-1 font-medium text-slate-600">
                        {{ $importCounts->get('skip', 0) }} übersprungen
                    </span>
                </div>

                <div class="mt-4 max-h-[28rem] overflow-auto rounded-lg border border-slate-200">
                    <table class="min-w-full text-left text-sm">
                        <thead class="sticky top-0 bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="px-3 py-2">Zeile</th>
                                <th class="px-3 py-2">Status</th>
                                <th class="px-3 py-2">Name</th>
                                <th class="px-3 py-2">Organisation</th>
                                <th class="px-3 py-2">E-Mail</th>
                                <th class="px-3 py-2">Telefon</th>
                                <th class="px-3 py-2">Notiz</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach($importPreview as $entry)
                                <tr wire:key="import-line-{{ $entry['line'] }}" class="{{ $entry['status'] === 'skip' ? 'text-slate-400' : 'text-slate-700' }}">
                                    <td class="px-3 py-2">{{ $entry['line'] }}</td>
                                    <td class="whitespace-nowrap px-3 py-2">
                                        @if($entry['status'] === 'create')
                                            <span class="font-medium text-green-700">Neu</span>
                                        @elseif($entry['status'] === 'update')
                                            <span class="font-medium text-blue-700">Aktualisieren</span>
                                        @else
                                            {{ $entry['reason'] }}
                                        @endif
                                    </td>
                                    <td class="px-3 py-2">{{ $entry['attributes']['surname'] }} {{ $entry['attributes']['name'] }}</td>
                                    <td class="px-3 py-2">{{ $entry['attributes']['organization'] }}</td>
                                    <td class="px-3 py-2">{{ $entry['attributes']['email'] }}</td>
                                    <td class="whitespace-nowrap px-3 py-2">{{ $entry['attributes']['phone'] }}</td>
                                    <td class="whitespace-pre-line px-3 py-2 text-xs">{{ $entry['attributes']['note'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="mt-4 flex justify-end">
                    <button
                        type="button"
                        wire:click="runImport"
                        wire:loading.attr="disabled"
                        wire:target="runImport"
                        @disabled($importCounts->get('create', 0) + $importCounts->get('update', 0) === 0)
                        class="rounded-lg bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white disabled:opacity-60"
                    >
                        {{ $importCounts->get('create', 0) + $importCounts->get('update', 0) }} Kontakte importieren
                    </button>
                </div>
            @endif

        </section>
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
