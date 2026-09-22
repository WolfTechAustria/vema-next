<div class="space-y-6">

    @include('partials.flash-messages')

    <div>
        <h2 class="text-2xl font-bold tracking-tight">
            {{ $invoiceID ? 'Rechnung bearbeiten' : 'Rechnung erstellen' }}
        </h2>

        <p class="mt-1 text-sm text-slate-500">
            Kann jederzeit als Entwurf gespeichert werden. Erst mit "Rechnung erstellen" wird die
            Rechnungsnummer vergeben und das PDF erzeugt — danach ist die Rechnung nicht mehr bearbeitbar.
        </p>
    </div>

    <datalist id="articles-list">
        @foreach($articles as $article)
            <option value="{{ $article->name }}"></option>
        @endforeach
    </datalist>

    <div class="grid gap-6 xl:grid-cols-[1fr_320px]">

        <div class="space-y-6">

            {{-- Empfänger --}}
            <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">

                <h3 class="mb-4 font-semibold text-slate-900">Empfänger</h3>

                @if($selectedRecipient)
                    <div class="flex items-center justify-between rounded-lg border border-slate-200 bg-slate-50 px-4 py-3">
                        <div>
                            <div class="font-medium text-slate-900">{{ $selectedRecipient->display_name }}</div>
                            @if($selectedRecipient->email)
                                <div class="text-sm text-slate-500">{{ $selectedRecipient->email }}</div>
                            @endif
                        </div>

                        <button type="button" wire:click="changeRecipient" class="text-sm font-medium text-slate-600 hover:text-slate-900">
                            Ändern
                        </button>
                    </div>
                @elseif($showNewRecipientForm)

                    <div class="grid gap-3 sm:grid-cols-2">

                        <div class="sm:col-span-2">
                            <label class="mb-1 block text-xs font-medium text-slate-500">Firma / Organisation</label>
                            <input type="text" wire:model="newRecipientCompanyName" class="w-full rounded-lg border border-slate-300 px-2 py-1.5 text-sm">
                            @error('newRecipientCompanyName')
                                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label class="mb-1 block text-xs font-medium text-slate-500">Vorname</label>
                            <input type="text" wire:model="newRecipientName" class="w-full rounded-lg border border-slate-300 px-2 py-1.5 text-sm">
                        </div>

                        <div>
                            <label class="mb-1 block text-xs font-medium text-slate-500">Nachname</label>
                            <input type="text" wire:model="newRecipientSurname" class="w-full rounded-lg border border-slate-300 px-2 py-1.5 text-sm">
                        </div>

                        <div class="sm:col-span-2">
                            <label class="mb-1 block text-xs font-medium text-slate-500">Straße</label>
                            <input type="text" wire:model="newRecipientStreet" class="w-full rounded-lg border border-slate-300 px-2 py-1.5 text-sm">
                        </div>

                        <div>
                            <label class="mb-1 block text-xs font-medium text-slate-500">PLZ</label>
                            <input type="text" wire:model="newRecipientZip" class="w-full rounded-lg border border-slate-300 px-2 py-1.5 text-sm">
                        </div>

                        <div>
                            <label class="mb-1 block text-xs font-medium text-slate-500">Ort</label>
                            <input type="text" wire:model="newRecipientCity" class="w-full rounded-lg border border-slate-300 px-2 py-1.5 text-sm">
                        </div>

                        <div>
                            <label class="mb-1 block text-xs font-medium text-slate-500">E-Mail</label>
                            <input type="email" wire:model="newRecipientEmail" class="w-full rounded-lg border border-slate-300 px-2 py-1.5 text-sm">
                            @error('newRecipientEmail')
                                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label class="mb-1 block text-xs font-medium text-slate-500">Telefon</label>
                            <input type="text" wire:model="newRecipientPhone" class="w-full rounded-lg border border-slate-300 px-2 py-1.5 text-sm">
                        </div>

                        <div class="sm:col-span-2">
                            <label class="mb-1 block text-xs font-medium text-slate-500">UID-Nummer</label>
                            <input type="text" wire:model="newRecipientVatId" class="w-full rounded-lg border border-slate-300 px-2 py-1.5 text-sm">
                        </div>

                    </div>

                    <div class="mt-4 flex gap-2">
                        <button type="button" wire:click="saveNewRecipient" wire:loading.attr="disabled" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white disabled:opacity-60">
                            Anlegen &amp; übernehmen
                        </button>

                        <button type="button" wire:click="cancelNewRecipient" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700">
                            Abbrechen
                        </button>
                    </div>

                @else
                    <input
                        type="text"
                        wire:model.live.debounce.300ms="recipientSearch"
                        placeholder="Empfänger suchen …"
                        class="w-full rounded-lg border border-slate-300 px-3 py-2"
                    >

                    <div class="mt-3 divide-y divide-slate-100 rounded-lg border border-slate-200">
                        @forelse($availableRecipients as $recipient)
                            <button
                                type="button"
                                wire:click="selectRecipient({{ $recipient->recipientID }})"
                                class="flex w-full items-center justify-between px-4 py-2 text-left text-sm hover:bg-slate-50"
                            >
                                <span>{{ $recipient->display_name }}</span>
                                <span class="text-xs text-slate-400">#{{ $recipient->recipientID }}</span>
                            </button>
                        @empty
                            @if(trim($recipientSearch) !== '')
                                <div class="px-4 py-3 text-sm text-slate-500">
                                    Keine passenden Empfänger gefunden.
                                </div>
                            @endif
                        @endforelse
                    </div>

                    <button type="button" wire:click="showRecipientCreateForm" class="mt-3 text-sm font-medium text-slate-900 underline">
                        + Neuen Empfänger anlegen
                    </button>
                @endif

                @error('recipientID')
                    <p class="mt-2 text-xs text-red-600">{{ $message }}</p>
                @enderror

            </section>

            {{-- Rechnungsdaten --}}
            <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">

                <h3 class="mb-4 font-semibold text-slate-900">Rechnungsdaten</h3>

                <div class="grid gap-4 md:grid-cols-2">

                    <div>
                        <label class="mb-1 block text-sm font-medium">Rechnungsdatum</label>
                        <input type="date" wire:model="invoice_date" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                        @error('invoice_date')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium">Fällig bis</label>
                        <input type="date" wire:model="due_date" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                    </div>

                    <div class="md:col-span-2">
                        <label class="mb-1 block text-sm font-medium">Anlass</label>
                        <input type="text" wire:model="purpose" placeholder="z. B. Dorfmeisterschaft 2026" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                    </div>

                    <div class="md:col-span-2">
                        <label class="mb-1 block text-sm font-medium">Einleitungstext</label>
                        <textarea wire:model="intro_text" rows="2" class="w-full rounded-lg border border-slate-300 px-3 py-2"></textarea>
                    </div>

                    <div class="md:col-span-2">
                        <label class="flex items-center gap-3">
                            <input type="checkbox" wire:model="small_business_no_vat" class="h-4 w-4 rounded border-slate-300">
                            <span class="text-sm font-medium text-slate-700">Kleinunternehmer gem. § 6 Abs. 1 Z 27 UStG — keine USt. ausweisen</span>
                        </label>
                    </div>

                </div>

            </section>

            {{-- Positionen --}}
            <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">

                <div class="mb-4 flex items-center justify-between">
                    <h3 class="font-semibold text-slate-900">Positionen</h3>

                    <button type="button" wire:click="addItem" class="text-sm font-medium text-slate-600 hover:text-slate-900">
                        + Position hinzufügen
                    </button>
                </div>

                @error('items')
                    <p class="mb-3 text-xs text-red-600">{{ $message }}</p>
                @enderror

                <div class="overflow-x-auto">
                    <table class="w-full min-w-[720px] border-separate border-spacing-y-1 text-sm">
                        <thead>
                        <tr class="text-left text-xs font-medium uppercase tracking-wide text-slate-500">
                            <th class="px-2 pb-1">Bezeichnung</th>
                            <th class="w-20 px-2 pb-1">Menge</th>
                            <th class="w-20 px-2 pb-1">Einheit</th>
                            <th class="w-28 px-2 pb-1">Preis netto</th>
                            <th class="w-20 px-2 pb-1">Rabatt %</th>
                            <th class="w-20 px-2 pb-1">USt. %</th>
                            <th class="w-24 px-2 pb-1 text-right">Summe</th>
                            <th class="w-10 px-2 pb-1"></th>
                        </tr>
                        </thead>

                        <tbody>
                        @foreach($items as $index => $item)
                            <tr wire:key="item-{{ $index }}" class="align-top">

                                <td class="px-2 py-1">
                                    <input
                                        type="text"
                                        list="articles-list"
                                        wire:model="items.{{ $index }}.description"
                                        wire:change="fillFromArticle({{ $index }})"
                                        class="w-full rounded-lg border border-slate-300 px-2 py-1.5 text-sm"
                                    >
                                    @error('items.' . $index . '.description')
                                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                    @enderror
                                </td>

                                <td class="px-2 py-1">
                                    <input type="text" wire:model.live="items.{{ $index }}.quantity" class="w-full rounded-lg border border-slate-300 px-2 py-1.5 text-sm">
                                    @error('items.' . $index . '.quantity')
                                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                    @enderror
                                </td>

                                <td class="px-2 py-1">
                                    <input type="text" wire:model="items.{{ $index }}.unit" class="w-full rounded-lg border border-slate-300 px-2 py-1.5 text-sm">
                                </td>

                                <td class="px-2 py-1">
                                    <input type="text" wire:model.live="items.{{ $index }}.price_net" class="w-full rounded-lg border border-slate-300 px-2 py-1.5 text-sm">
                                    @error('items.' . $index . '.price_net')
                                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                    @enderror
                                </td>

                                <td class="px-2 py-1">
                                    <input type="text" wire:model.live="items.{{ $index }}.discount_percent" class="w-full rounded-lg border border-slate-300 px-2 py-1.5 text-sm">
                                </td>

                                <td class="px-2 py-1">
                                    <input type="text" wire:model.live="items.{{ $index }}.tax_rate" class="w-full rounded-lg border border-slate-300 px-2 py-1.5 text-sm">
                                    @error('items.' . $index . '.tax_rate')
                                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                    @enderror
                                </td>

                                <td class="px-2 py-3 text-right font-medium text-slate-700 whitespace-nowrap">
                                    {{ number_format($itemLineTotals[$index]['gross'] ?? 0, 2, ',', '.') }} €
                                </td>

                                <td class="px-2 py-1 text-right">
                                    @if(count($items) > 1)
                                        <button type="button" wire:click="removeItem({{ $index }})" class="px-1 py-1.5 text-sm font-medium text-red-600 hover:text-red-800" title="Position entfernen">
                                            ✕
                                        </button>
                                    @endif
                                </td>

                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>

            </section>

            <div class="md:col-span-2">
                <label class="mb-1 block text-sm font-medium">Fußtext</label>
                <textarea wire:model="footer_text" rows="3" class="w-full rounded-lg border border-slate-300 px-3 py-2"></textarea>
            </div>

        </div>

        {{-- Summen + Aktionen --}}
        <div class="space-y-6">

            <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">

                <h3 class="mb-4 font-semibold text-slate-900">Vorschau Summen</h3>

                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between">
                        <dt class="text-slate-500">Zwischensumme netto</dt>
                        <dd>{{ number_format($previewTotals['subtotal_net'], 2, ',', '.') }} €</dd>
                    </div>

                    <div class="flex justify-between">
                        <dt class="text-slate-500">Rabatt</dt>
                        <dd>- {{ number_format($previewTotals['discount_total'], 2, ',', '.') }} €</dd>
                    </div>

                    @unless($small_business_no_vat)
                        <div class="flex justify-between">
                            <dt class="text-slate-500">USt.</dt>
                            <dd>{{ number_format($previewTotals['tax_total'], 2, ',', '.') }} €</dd>
                        </div>
                    @endunless

                    <div class="flex justify-between border-t border-slate-200 pt-2 font-semibold text-slate-900">
                        <dt>Gesamtbetrag</dt>
                        <dd>{{ number_format($previewTotals['total_gross'], 2, ',', '.') }} €</dd>
                    </div>
                </dl>

            </section>

            <section class="space-y-3">
                <button
                    type="button"
                    wire:click="saveAndFinalize"
                    wire:confirm="Rechnungsnummer vergeben und PDF erzeugen? Danach kann die Rechnung nicht mehr bearbeitet werden."
                    wire:loading.attr="disabled"
                    class="w-full rounded-lg bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white disabled:opacity-60"
                >
                    Rechnung erstellen
                </button>

                <button
                    type="button"
                    wire:click="saveDraft"
                    wire:loading.attr="disabled"
                    class="w-full rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 disabled:opacity-60"
                >
                    Als Entwurf speichern
                </button>
            </section>

        </div>

    </div>

</div>
