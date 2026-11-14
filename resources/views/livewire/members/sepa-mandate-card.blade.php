<div>
    @include('partials.flash-messages')

    @if($available)
        <section class="rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 px-6 py-4">
                <h3 class="font-semibold text-slate-900">SEPA-Lastschrift</h3>

                @if($isTreasurer && ! $editing)
                    <div class="flex items-center gap-3">
                        <button type="button" wire:click="startEditing" class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50">
                            {{ $mandate ? 'Neues Mandat erfassen' : 'Mandat erfassen' }}
                        </button>
                        @if($mandate)
                            <button type="button" wire:click="revoke" wire:confirm="Mandat {{ $mandate->reference }} widerrufen? Danach kann nicht mehr eingezogen werden." class="text-sm text-red-600 hover:text-red-800">
                                Widerrufen
                            </button>
                        @endif
                    </div>
                @endif
            </div>

            <div class="px-6 py-4 text-sm">
                @if(! $mandate)
                    <p class="text-slate-500">Kein Lastschriftmandat – der Beitrag wird nicht eingezogen.</p>
                @elseif(! $isTreasurer)
                    <p class="text-slate-700">Lastschrift: <strong>ja</strong> (Mandat vorhanden)</p>
                @else
                    <dl class="grid gap-x-6 gap-y-2 sm:grid-cols-2">
                        <div><dt class="text-slate-500">Kontoinhaber</dt><dd class="font-medium text-slate-900">{{ $mandate->account_holder }}</dd></div>
                        <div><dt class="text-slate-500">IBAN</dt><dd class="font-medium text-slate-900">{{ $mandate->formattedIban() }}</dd></div>
                        <div><dt class="text-slate-500">BIC</dt><dd class="text-slate-900">{{ $mandate->bic ?: '–' }}</dd></div>
                        <div><dt class="text-slate-500">Mandatsreferenz</dt><dd class="text-slate-900">{{ $mandate->reference }}</dd></div>
                        <div><dt class="text-slate-500">Unterschrieben am</dt><dd class="text-slate-900">{{ $mandate->signed_on->format('d.m.Y') }}</dd></div>
                        <div><dt class="text-slate-500">Erfasst</dt><dd class="text-slate-900">{{ $mandate->source === 'portal' ? 'Online im Mitgliederbereich' : 'Durch den Verein' }}{{ $mandate->first_used_at ? ', bereits genutzt' : '' }}</dd></div>
                    </dl>
                @endif

                @if($editing)
                    <form wire:submit="save" class="mt-4 grid gap-4 rounded-lg border border-slate-200 bg-slate-50 p-4 sm:grid-cols-2">
                        <div>
                            <label for="sepa-holder" class="mb-1 block font-medium text-slate-700">Kontoinhaber</label>
                            <input id="sepa-holder" type="text" wire:model="accountHolder" maxlength="70" class="w-full rounded-lg border-slate-300 text-sm">
                            @error('accountHolder') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="sepa-iban" class="mb-1 block font-medium text-slate-700">IBAN</label>
                            <input id="sepa-iban" type="text" wire:model="iban" autocomplete="off" placeholder="AT.." class="w-full rounded-lg border-slate-300 text-sm uppercase">
                            @error('iban') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="sepa-bic" class="mb-1 block font-medium text-slate-700">BIC <span class="font-normal text-slate-400">(optional)</span></label>
                            <input id="sepa-bic" type="text" wire:model="bic" autocomplete="off" class="w-full rounded-lg border-slate-300 text-sm uppercase">
                            @error('bic') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="sepa-signed" class="mb-1 block font-medium text-slate-700">Unterschrieben am</label>
                            <input id="sepa-signed" type="date" wire:model="signedOn" class="w-full rounded-lg border-slate-300 text-sm">
                            @error('signedOn') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div class="flex gap-2 sm:col-span-2">
                            <button type="submit" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Mandat speichern</button>
                            <button type="button" wire:click="cancelEditing" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Abbrechen</button>
                        </div>
                        @if($mandate)
                            <p class="text-xs text-slate-500 sm:col-span-2">Das bisherige Mandat {{ $mandate->reference }} wird dabei widerrufen.</p>
                        @endif
                    </form>
                @endif
            </div>
        </section>
    @endif
</div>
