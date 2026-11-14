<div class="space-y-6">

    @include('partials.flash-messages')

    @include('partials.admin-settings-nav')

    <div>
        <h2 class="text-2xl font-bold tracking-tight">SEPA-Lastschrift</h2>
        <p class="mt-1 text-sm text-slate-500">
            Mitgliedsbeiträge per Lastschrift einziehen: unter <strong>Mitgliedsbeiträge → SEPA-Lastschrift</strong> entsteht eine Datei für das Online-Banking.
        </p>
    </div>

    <form wire:submit="save" class="space-y-6">
        <section class="grid gap-6 rounded-xl border border-slate-200 bg-white p-6 shadow-sm sm:grid-cols-2">
            <div>
                <label for="sepa-creditor" class="mb-1 block text-sm font-medium text-slate-700">Gläubiger-ID (Creditor-ID)</label>
                <input id="sepa-creditor" type="text" wire:model="creditorId" placeholder="AT61ZZZ01234567890" class="w-full rounded-lg border border-slate-300 px-3 py-2 uppercase">
                <p class="mt-1 text-xs text-slate-500">Erhalten Sie bei Ihrer Hausbank (in Österreich über die OeNB vergeben).</p>
                @error('creditorId') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <span class="mb-1 block text-sm font-medium text-slate-700">Konto des Vereins</span>
                <p class="rounded-lg bg-slate-50 px-3 py-2 text-sm text-slate-700">
                    {{ $clubIban ?: 'Keine IBAN hinterlegt' }}
                </p>
                <p class="mt-1 text-xs text-slate-500">
                    IBAN und BIC unter <a href="{{ route('admin.settings.index') }}" wire:navigate class="underline">Allgemein</a>.
                </p>
            </div>

            <div>
                <label for="sepa-cashbook" class="mb-1 block text-sm font-medium text-slate-700">Kassabuch beim Bestätigen</label>
                <select id="sepa-cashbook" wire:model="cashBookMode" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2">
                    @foreach($cashBookModes as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
                <p class="mt-1 text-xs text-slate-500">Einnahme „Mitgliedsbeiträge“ am Einzugstag, sobald ein Einzug bestätigt wird.</p>
            </div>

            <div>
                <label for="sepa-lead" class="mb-1 block text-sm font-medium text-slate-700">Vorlauf für Einzugsdatum (Bankarbeitstage)</label>
                <input id="sepa-lead" type="number" min="1" max="30" wire:model="leadDays" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                <p class="mt-1 text-xs text-slate-500">Vorschlag für das Einzugsdatum – genug Zeit für die Vorabinformation der Mitglieder.</p>
                @error('leadDays') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="sm:col-span-2">
                <label for="sepa-text" class="mb-1 block text-sm font-medium text-slate-700">Mandatstext im Mitgliederbereich <span class="font-normal text-slate-400">(optional)</span></label>
                <textarea id="sepa-text" wire:model="mandateText" rows="4" placeholder="Leer = Standardtext" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></textarea>
                <p class="mt-1 text-xs text-slate-500">Platzhalter: <code>:verein</code>, <code>:glaeubiger_id</code>. Vorschau:</p>
                <p class="mt-1 rounded-lg bg-slate-50 px-3 py-2 text-xs text-slate-600">{{ $previewText }}</p>
                @error('mandateText') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
        </section>

        <button type="submit" class="rounded-lg bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-800">
            Speichern
        </button>
    </form>

</div>
