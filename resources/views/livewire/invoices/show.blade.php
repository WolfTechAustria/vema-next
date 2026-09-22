<div class="space-y-6">

    @include('partials.flash-messages')

    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <div class="flex items-center gap-3">
                <h2 class="text-2xl font-bold tracking-tight">
                    {{ $invoice->invoice_number ?? 'Entwurf #' . $invoice->invoiceID }}
                </h2>

                @php
                    $statusLabels = [
                        'draft' => ['Entwurf', 'bg-slate-100 text-slate-600'],
                        'sent' => ['Offen', 'bg-amber-50 text-amber-700'],
                        'paid' => ['Bezahlt', 'bg-green-50 text-green-700'],
                        'canceled' => ['Storniert', 'bg-red-50 text-red-700'],
                    ];
                    [$label, $classes] = $statusLabels[$invoice->status] ?? [$invoice->status, 'bg-slate-100 text-slate-600'];
                @endphp

                <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold {{ $classes }}">
                    {{ $label }}
                </span>
            </div>

            <p class="mt-1 text-sm text-slate-500">
                {{ $invoice->recipient?->display_name }} · {{ $invoice->invoice_date->format('d.m.Y') }}
                @if($invoice->purpose)
                    · {{ $invoice->purpose }}
                @endif
            </p>
        </div>

        <div class="flex flex-wrap gap-2">

            @if($invoice->isDraft())
                <a href="{{ route('invoices.edit', $invoice) }}" wire:navigate class="rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700">
                    Bearbeiten
                </a>

                <button
                    type="button"
                    wire:click="finalize"
                    wire:confirm="Rechnungsnummer vergeben und PDF erzeugen? Danach kann die Rechnung nicht mehr bearbeitet werden."
                    class="rounded-lg bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white"
                >
                    Rechnung erstellen
                </button>
            @else
                <a href="{{ route('invoices.pdf', $invoice) }}" target="_blank" class="rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700">
                    PDF ansehen
                </a>

                @if($invoice->status === 'sent')
                    <button type="button" wire:click="sendEmail" class="rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700">
                        Per E-Mail senden
                    </button>

                    <button type="button" wire:click="markPaid" class="rounded-lg bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white">
                        Als bezahlt markieren
                    </button>

                    <button
                        type="button"
                        wire:click="cancel"
                        wire:confirm="Rechnung wirklich stornieren?"
                        class="rounded-lg border border-red-300 px-4 py-2.5 text-sm font-semibold text-red-700"
                    >
                        Stornieren
                    </button>
                @endif
            @endif

        </div>

    </div>

    <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">

        <div class="grid gap-6 md:grid-cols-2">

            <div>
                <h3 class="mb-2 text-sm font-semibold uppercase tracking-wide text-slate-500">Empfänger</h3>

                <div class="text-sm text-slate-700">
                    {{ $invoice->recipient?->display_name }}<br>
                    @if($invoice->recipient?->street)
                        {{ $invoice->recipient->street }}<br>
                    @endif
                    {{ trim($invoice->recipient?->zip . ' ' . $invoice->recipient?->city) }}<br>
                    @if($invoice->recipient?->email)
                        {{ $invoice->recipient->email }}
                    @endif
                </div>
            </div>

            <div>
                <h3 class="mb-2 text-sm font-semibold uppercase tracking-wide text-slate-500">Details</h3>

                <dl class="space-y-1 text-sm text-slate-700">
                    @if($invoice->due_date)
                        <div class="flex justify-between"><dt class="text-slate-500">Fällig bis</dt><dd>{{ $invoice->due_date->format('d.m.Y') }}</dd></div>
                    @endif

                    @if($invoice->sent_at)
                        <div class="flex justify-between"><dt class="text-slate-500">Versendet am</dt><dd>{{ $invoice->sent_at->format('d.m.Y H:i') }}</dd></div>
                    @endif

                    @if($invoice->paid_at)
                        <div class="flex justify-between"><dt class="text-slate-500">Bezahlt am</dt><dd>{{ $invoice->paid_at->format('d.m.Y') }}</dd></div>
                    @endif

                    @if($invoice->creator)
                        <div class="flex justify-between"><dt class="text-slate-500">Erstellt von</dt><dd>{{ $invoice->creator->username }}</dd></div>
                    @endif
                </dl>
            </div>

        </div>

    </section>

    <section class="rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Bezeichnung</th>
                    <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Menge</th>
                    <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Preis netto</th>
                    <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">USt.</th>
                    <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Gesamt</th>
                </tr>
                </thead>

                <tbody class="divide-y divide-slate-100">
                @foreach($invoice->items as $item)
                    <tr>
                        <td class="px-6 py-3 text-slate-900">{{ $item->description }}</td>
                        <td class="px-6 py-3 text-right text-slate-600">{{ number_format((float) $item->quantity, 2, ',', '.') }} {{ $item->unit }}</td>
                        <td class="px-6 py-3 text-right text-slate-600">{{ number_format((float) $item->price_net, 2, ',', '.') }} €</td>
                        <td class="px-6 py-3 text-right text-slate-600">
                            {{ $invoice->small_business_no_vat ? '–' : number_format((float) $item->tax_rate, 2, ',', '.') . ' %' }}
                        </td>
                        <td class="px-6 py-3 text-right text-slate-900">{{ number_format((float) $item->line_total_gross, 2, ',', '.') }} €</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>

        <div class="border-t border-slate-200 p-6">
            <dl class="ml-auto max-w-xs space-y-2 text-sm">
                <div class="flex justify-between">
                    <dt class="text-slate-500">Zwischensumme netto</dt>
                    <dd>{{ number_format((float) $invoice->subtotal_net, 2, ',', '.') }} €</dd>
                </div>

                @if((float) $invoice->discount_total > 0)
                    <div class="flex justify-between">
                        <dt class="text-slate-500">Rabatt</dt>
                        <dd>- {{ number_format((float) $invoice->discount_total, 2, ',', '.') }} €</dd>
                    </div>
                @endif

                @unless($invoice->small_business_no_vat)
                    <div class="flex justify-between">
                        <dt class="text-slate-500">USt.</dt>
                        <dd>{{ number_format((float) $invoice->tax_total, 2, ',', '.') }} €</dd>
                    </div>
                @endunless

                <div class="flex justify-between border-t border-slate-200 pt-2 font-semibold text-slate-900">
                    <dt>Gesamtbetrag</dt>
                    <dd>{{ number_format((float) $invoice->total_gross, 2, ',', '.') }} €</dd>
                </div>
            </dl>
        </div>
    </section>

</div>
