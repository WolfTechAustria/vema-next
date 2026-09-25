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

    <section class="max-w-2xl rounded-xl border border-slate-200 bg-white p-6 shadow-sm">

        <h3 class="text-lg font-semibold">Testmodus</h3>

        <p class="mt-1 text-sm text-slate-500">
            Benutzer können sich mit ihrem normalen Login in einer Kopie der Live-Daten umsehen und alles ausprobieren.
            Änderungen im Testmodus betreffen nicht die Live-Daten, Mails gehen nur an den Tester selbst.
        </p>

        @if($isDemoActive)

            <div class="mt-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                Du bist gerade im Testmodus. Diese Einstellungen können nur außerhalb des Testmodus geändert werden.
            </div>

        @else

            <div class="mt-4 space-y-4">

                <label class="flex items-center gap-3">
                    <input type="checkbox" wire:model="demo_enabled" class="h-4 w-4 rounded border-slate-300">
                    <span class="text-sm font-medium text-slate-700">Testmodus für Benutzer verfügbar</span>
                </label>

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Testdaten zurücksetzen</label>
                    <select wire:model="demo_reset_mode" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                        @foreach($demoResetModes as $mode)
                            <option value="{{ $mode->value }}">{{ $mode->label() }}</option>
                        @endforeach
                    </select>
                    @error('demo_reset_mode')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <p class="text-sm text-slate-500">
                    Letzter Reset:
                    <span class="font-medium text-slate-700">{{ $demoLastResetAt?->format('d.m.Y H:i') ?? 'noch nie' }}</span>
                </p>

            </div>

            <div class="mt-6 flex flex-wrap gap-3">
                <button
                    type="button"
                    wire:click="saveDemoSettings"
                    wire:loading.attr="disabled"
                    wire:target="saveDemoSettings"
                    class="rounded-lg bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white disabled:opacity-60"
                >
                    Speichern
                </button>

                <button
                    type="button"
                    wire:click="resetDemoData"
                    wire:confirm="Alle Änderungen im Testmodus verwerfen und die Testdaten jetzt auf den Live-Stand zurücksetzen?"
                    wire:loading.attr="disabled"
                    wire:target="resetDemoData"
                    class="rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50 disabled:opacity-60"
                >
                    <span wire:loading.remove wire:target="resetDemoData">Jetzt auf Live-Stand zurücksetzen</span>
                    <span wire:loading wire:target="resetDemoData">Wird zurückgesetzt …</span>
                </button>
            </div>

        @endif

    </section>

</div>
