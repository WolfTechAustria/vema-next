<div class="space-y-6">

    @include('partials.flash-messages')

    @include('partials.admin-settings-nav')

    <div>
        <h2 class="text-2xl font-bold tracking-tight">
            Einstellungen
        </h2>

        <p class="mt-1 text-sm text-slate-500">
            Vereinsdaten, die in E-Mails und Dokumenten (Briefkopf, Signatur, Login-Seite) verwendet werden.
        </p>
    </div>

    <section class="max-w-2xl rounded-xl border border-slate-200 bg-white p-6 shadow-sm">

        <div class="grid gap-4 md:grid-cols-2">

            <div class="md:col-span-2">
                <label class="mb-1 block text-sm font-medium text-slate-700">
                    Vereinsname
                </label>

                <input type="text" wire:model="name" class="w-full rounded-lg border border-slate-300 px-3 py-2">

                @error('name')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="md:col-span-2">
                <label class="mb-1 block text-sm font-medium text-slate-700">
                    Straße
                </label>

                <input type="text" wire:model="street" class="w-full rounded-lg border border-slate-300 px-3 py-2">
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">
                    PLZ
                </label>

                <input type="text" wire:model="zip" class="w-full rounded-lg border border-slate-300 px-3 py-2">
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">
                    Ort
                </label>

                <input type="text" wire:model="city" class="w-full rounded-lg border border-slate-300 px-3 py-2">
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">
                    E-Mail-Adresse
                </label>

                <input type="email" wire:model="email" class="w-full rounded-lg border border-slate-300 px-3 py-2">

                @error('email')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">
                    Telefon
                </label>

                <input type="text" wire:model="phone" class="w-full rounded-lg border border-slate-300 px-3 py-2">
            </div>

            <div class="md:col-span-2">
                <label class="mb-1 block text-sm font-medium text-slate-700">
                    Website
                </label>

                <input type="text" wire:model="website" class="w-full rounded-lg border border-slate-300 px-3 py-2">
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
                <span wire:loading.remove wire:target="save">Speichern</span>
                <span wire:loading wire:target="save">Speichern …</span>
            </button>
        </div>

    </section>

</div>
