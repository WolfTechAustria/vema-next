<div class="space-y-6">

    @include('partials.flash-messages')

    @include('partials.invoices-nav')

    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h2 class="text-2xl font-bold tracking-tight">
                Rechnungsempfänger
            </h2>

            <p class="mt-1 text-sm text-slate-500">
                Sponsoren und sonstige Empfänger für Rechnungen verwalten.
            </p>
        </div>

        @unless($showForm)
            <button
                type="button"
                wire:click="create"
                class="rounded-lg bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white"
            >
                + Empfänger erstellen
            </button>
        @endunless
    </div>

    @if($showForm)

        <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">

            <div class="flex items-center justify-between">
                <h3 class="text-lg font-semibold">
                    {{ $editingRecipientID ? 'Empfänger bearbeiten' : 'Neuer Empfänger' }}
                </h3>

                <button type="button" wire:click="cancelEdit" class="text-sm font-medium text-slate-500 hover:text-slate-800">
                    Abbrechen
                </button>
            </div>

            <div class="mt-5 grid gap-4 md:grid-cols-2">

                <div class="md:col-span-2">
                    <label class="mb-1 block text-sm font-medium">Firma / Organisation</label>
                    <input type="text" wire:model="company_name" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                    @error('company_name')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium">Vorname</label>
                    <input type="text" wire:model="name" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium">Nachname</label>
                    <input type="text" wire:model="surname" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                </div>

                <div class="md:col-span-2">
                    <label class="mb-1 block text-sm font-medium">Straße</label>
                    <input type="text" wire:model="street" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium">PLZ</label>
                    <input type="text" wire:model="zip" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium">Ort</label>
                    <input type="text" wire:model="city" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium">E-Mail</label>
                    <input type="email" wire:model="email" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                    @error('email')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium">Telefon</label>
                    <input type="text" wire:model="phone" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                </div>

                <div class="md:col-span-2">
                    <label class="mb-1 block text-sm font-medium">UID-Nummer</label>
                    <input type="text" wire:model="vat_id" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                </div>

                <div class="md:col-span-2">
                    <label class="mb-1 block text-sm font-medium">Notiz</label>
                    <textarea wire:model="note" rows="3" class="w-full rounded-lg border border-slate-300 px-3 py-2"></textarea>
                </div>

                <div class="md:col-span-2">
                    <label class="flex items-center gap-3">
                        <input type="checkbox" wire:model="active" class="h-4 w-4 rounded border-slate-300">
                        <span class="text-sm font-medium text-slate-700">Empfänger aktiv</span>
                    </label>
                </div>

            </div>

            <div class="mt-6">
                <button
                    type="button"
                    wire:click="save"
                    wire:loading.attr="disabled"
                    wire:target="save"
                    class="rounded-lg bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white disabled:opacity-60"
                >
                    <span wire:loading.remove wire:target="save">
                        {{ $editingRecipientID ? 'Änderungen speichern' : 'Empfänger anlegen' }}
                    </span>
                    <span wire:loading wire:target="save">Speichern …</span>
                </button>
            </div>

        </section>

    @endif

    {{-- Liste --}}
    <section class="rounded-xl border border-slate-200 bg-white shadow-sm">

        <div class="border-b border-slate-200 p-4">
            <input
                type="search"
                wire:model.live.debounce.300ms="search"
                placeholder="Firma, Name oder E-Mail suchen …"
                class="w-full max-w-sm rounded-lg border border-slate-300 px-3 py-2"
            >
        </div>

        <div class="divide-y divide-slate-100">
            @forelse($recipients as $recipient)
                <div wire:key="recipient-{{ $recipient->recipientID }}" class="flex items-start justify-between gap-4 px-6 py-4">

                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <div class="font-medium text-slate-900">
                                {{ $recipient->display_name }}
                            </div>

                            @if($recipient->active)
                                <span class="rounded-full bg-green-50 px-2 py-0.5 text-xs font-medium text-green-700">Aktiv</span>
                            @else
                                <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-500">Inaktiv</span>
                            @endif
                        </div>

                        <div class="mt-2 space-y-1 text-sm text-slate-600">
                            @if($recipient->email)
                                <div>{{ $recipient->email }}</div>
                            @endif

                            @if($recipient->zip || $recipient->city)
                                <div>{{ trim($recipient->zip . ' ' . $recipient->city) }}</div>
                            @endif
                        </div>
                    </div>

                    <div class="flex shrink-0 items-center gap-3">
                        <button type="button" wire:click="edit({{ $recipient->recipientID }})" class="text-sm font-medium text-slate-600 hover:text-slate-900">
                            Bearbeiten
                        </button>

                        <button type="button" wire:click="toggleActive({{ $recipient->recipientID }})" class="text-sm font-medium text-slate-600 hover:text-slate-900">
                            {{ $recipient->active ? 'Deaktivieren' : 'Aktivieren' }}
                        </button>
                    </div>

                </div>
            @empty
                <div class="px-6 py-10 text-center text-sm text-slate-500">
                    Keine Rechnungsempfänger gefunden.
                </div>
            @endforelse
        </div>

    </section>

</div>
