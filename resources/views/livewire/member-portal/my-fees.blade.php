<div class="mx-auto max-w-4xl space-y-6">

    @include('partials.flash-messages')

    @if($openAmount > 0)
        <section class="rounded-xl border border-amber-200 bg-amber-50 p-6 shadow-sm">
            <h3 class="font-semibold text-amber-900">
                Offener Betrag
            </h3>
            <p class="mt-1 text-sm text-amber-800">
                Derzeit sind
                <strong>€ {{ number_format($openAmount, 2, ',', '.') }}</strong>
                an Mitgliedsbeiträgen offen.
            </p>
        </section>
    @endif

    @if($mandatesAvailable)
        <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h3 class="font-semibold text-slate-900">Lastschrift</h3>
                    @if($mandate)
                        <p class="mt-1 text-sm text-slate-600">
                            Deine Beiträge werden von <strong>{{ $mandate->maskedIban() }}</strong> ({{ $mandate->account_holder }}) eingezogen.
                            Mandatsreferenz {{ $mandate->reference }}, erteilt am {{ $mandate->signed_on->format('d.m.Y') }}.
                        </p>
                    @else
                        <p class="mt-1 text-sm text-slate-600">
                            Erteile dem Verein ein SEPA-Lastschriftmandat – dann werden deine Beiträge automatisch eingezogen und du musst nichts überweisen.
                        </p>
                    @endif
                </div>

                @unless($mandateFormOpen)
                    <div class="flex items-center gap-3">
                        <button type="button" wire:click="openMandateForm" class="rounded-lg {{ $mandate ? 'border border-slate-300 bg-white text-slate-700 hover:bg-slate-50' : 'bg-slate-900 text-white hover:bg-slate-800' }} px-4 py-2 text-sm font-semibold">
                            {{ $mandate ? 'Konto ändern' : 'Lastschrift erteilen' }}
                        </button>
                        @if($mandate)
                            <button type="button" wire:click="revokeMandate" wire:confirm="Lastschriftmandat widerrufen? Künftige Beiträge musst du dann selbst überweisen." class="text-sm text-red-600 hover:text-red-800">
                                Widerrufen
                            </button>
                        @endif
                    </div>
                @endunless
            </div>

            @if($mandateFormOpen)
                <form wire:submit="grantMandate" class="mt-4 space-y-4 rounded-lg border border-slate-200 bg-slate-50 p-4 text-sm">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label for="mandate-holder" class="mb-1 block font-medium text-slate-700">Kontoinhaber</label>
                            <input id="mandate-holder" type="text" wire:model="accountHolder" maxlength="70" autocomplete="name" class="w-full rounded-md border-slate-300 text-sm">
                            @error('accountHolder') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="mandate-iban" class="mb-1 block font-medium text-slate-700">IBAN</label>
                            <input id="mandate-iban" type="text" wire:model="iban" autocomplete="off" placeholder="AT.." class="w-full rounded-md border-slate-300 text-sm uppercase">
                            @error('iban') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="mandate-bic" class="mb-1 block font-medium text-slate-700">BIC <span class="font-normal text-slate-400">(optional)</span></label>
                            <input id="mandate-bic" type="text" wire:model="bic" autocomplete="off" class="w-full rounded-md border-slate-300 text-sm uppercase">
                            @error('bic') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <p class="rounded-md bg-white px-3 py-2 text-xs leading-relaxed text-slate-600">{{ $consentText }}</p>

                    <label class="flex items-start gap-2">
                        <input type="checkbox" wire:model="consent" class="mt-0.5 rounded border-slate-300">
                        <span>Ich erteile dieses SEPA-Lastschriftmandat.</span>
                    </label>
                    @error('consent') <p class="text-xs text-red-600">{{ $message }}</p> @enderror

                    <div class="flex gap-2">
                        <button type="submit" class="rounded-md bg-slate-900 px-4 py-2 font-semibold text-white hover:bg-slate-800">Mandat erteilen</button>
                        <button type="button" wire:click="closeMandateForm" class="rounded-md border border-slate-300 bg-white px-4 py-2 font-medium text-slate-700 hover:bg-slate-50">Abbrechen</button>
                    </div>
                </form>
            @endif
        </section>
    @endif

    <section class="rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-6 py-4">
            <h3 class="font-semibold text-slate-900">
                Mitgliedsbeiträge
            </h3>
            <p class="mt-1 text-sm text-slate-500">
                Übersicht deiner Beiträge und der dazu versendeten Vorschreibungen.
            </p>
        </div>

        @forelse($entries as $entry)
            <div class="border-b border-slate-100 px-6 py-4 last:border-b-0">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <div class="font-medium text-slate-900">
                            {{ $entry->year?->display_name ?? 'Mitgliedsbeitrag' }}
                        </div>
                        <div class="text-sm text-slate-500">
                            € {{ number_format((float) $entry->amount, 2, ',', '.') }}
                            @if($entry->status === 'paid' && $entry->paid_at)
                                · bezahlt am {{ $entry->paid_at->format('d.m.Y') }}
                            @elseif($entry->status === 'open' && $entry->year?->due_date)
                                · fällig am {{ $entry->year->due_date->format('d.m.Y') }}
                            @endif
                        </div>
                    </div>

                    <div>
                        @if($entry->status === 'paid')
                            <span class="rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-medium text-emerald-800">
                                Bezahlt
                            </span>
                        @elseif($entry->status === 'exempt')
                            <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-600">
                                Befreit
                            </span>
                        @elseif($entry->isPending())
                            <span class="rounded-full bg-sky-100 px-2.5 py-1 text-xs font-medium text-sky-800">
                                Wird per Lastschrift eingezogen
                            </span>
                        @else
                            <span class="rounded-full bg-amber-100 px-2.5 py-1 text-xs font-medium text-amber-800">
                                Offen
                            </span>
                        @endif
                    </div>
                </div>

                @if($entry->prescriptions->isNotEmpty())
                    <div class="mt-3 flex flex-wrap gap-2">
                        @foreach($entry->prescriptions as $prescription)
                            <a
                                href="{{ route('member.fees.document', $prescription) }}"
                                target="_blank"
                                class="inline-flex items-center gap-1 rounded-md border border-slate-300 px-2.5 py-1 text-xs font-medium text-slate-700 hover:bg-slate-50"
                            >
                                {{ $prescription->type === 'reminder'
                                    ? $prescription->reminder_level.'. Erinnerung'
                                    : 'Vorschreibung' }}
                                vom {{ $prescription->sent_at->format('d.m.Y') }}
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>
        @empty
            <p class="px-6 py-4 text-sm text-slate-500">
                Es sind keine Mitgliedsbeiträge vorhanden.
            </p>
        @endforelse
    </section>

</div>
