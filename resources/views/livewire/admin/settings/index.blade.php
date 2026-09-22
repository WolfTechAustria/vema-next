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

    </section>

    <section class="max-w-2xl rounded-xl border border-slate-200 bg-white p-6 shadow-sm">

        <h3 class="mb-4 font-semibold text-slate-900">Rechnungsdaten</h3>

        <div class="grid gap-4 md:grid-cols-2">

            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Bank</label>
                <input type="text" wire:model="bank_name" class="w-full rounded-lg border border-slate-300 px-3 py-2">
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">UID-Nummer</label>
                <input type="text" wire:model="vat_id" class="w-full rounded-lg border border-slate-300 px-3 py-2">
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">IBAN</label>
                <input type="text" wire:model="iban" class="w-full rounded-lg border border-slate-300 px-3 py-2">
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">BIC</label>
                <input type="text" wire:model="bic" class="w-full rounded-lg border border-slate-300 px-3 py-2">
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Standard-USt.-Satz für neue Positionen (%)</label>
                <input type="text" wire:model="invoice_default_tax_rate" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                @error('invoice_default_tax_rate')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex items-end">
                <label class="flex items-center gap-3">
                    <input type="checkbox" wire:model="small_business_default" class="h-4 w-4 rounded border-slate-300">
                    <span class="text-sm font-medium text-slate-700">Neue Rechnungen standardmäßig als Kleinunternehmer (keine USt.)</span>
                </label>
            </div>

            <div class="md:col-span-2">
                <label class="mb-1 block text-sm font-medium text-slate-700">Standard-Fußtext für neue Rechnungen</label>
                <textarea wire:model="invoice_footer_text" rows="3" class="w-full rounded-lg border border-slate-300 px-3 py-2"></textarea>
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
