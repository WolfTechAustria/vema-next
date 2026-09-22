<div class="space-y-6">

    @include('partials.flash-messages')

    @include('partials.invoices-nav')

    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h2 class="text-2xl font-bold tracking-tight">Rechnungen</h2>

            <p class="mt-1 text-sm text-slate-500">
                Offene Rechnungen (versendet, nicht bezahlt): {{ number_format($openTotal, 2, ',', '.') }} €
            </p>
        </div>

        <a
            href="{{ route('invoices.create') }}"
            wire:navigate
            class="rounded-lg bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white"
        >
            + Neue Rechnung
        </a>
    </div>

    <section class="rounded-xl border border-slate-200 bg-white shadow-sm">

        <div class="flex flex-col gap-3 border-b border-slate-200 p-4 sm:flex-row sm:items-center">

            <input
                type="search"
                wire:model.live.debounce.300ms="search"
                placeholder="Rechnungsnummer, Empfänger oder Anlass suchen …"
                class="w-full rounded-lg border border-slate-300 px-3 py-2 sm:max-w-sm"
            >

            <select wire:model.live="statusFilter" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <option value="all">Alle Status</option>
                <option value="draft">Entwurf</option>
                <option value="sent">Versendet / offen</option>
                <option value="paid">Bezahlt</option>
                <option value="canceled">Storniert</option>
            </select>

        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Nr.</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Empfänger</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Datum</th>
                    <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Betrag</th>
                    <th class="px-6 py-3 text-center text-xs font-semibold uppercase tracking-wide text-slate-500">Status</th>
                </tr>
                </thead>

                <tbody class="divide-y divide-slate-100">
                @forelse($invoices as $invoice)
                    <tr wire:key="invoice-{{ $invoice->invoiceID }}" class="cursor-pointer hover:bg-slate-50" onclick="window.location='{{ route('invoices.show', $invoice) }}'">
                        <td class="px-6 py-4 font-medium text-slate-900">
                            {{ $invoice->invoice_number ?? 'Entwurf #' . $invoice->invoiceID }}
                        </td>

                        <td class="px-6 py-4 text-slate-600">
                            {{ $invoice->recipient?->display_name ?? '–' }}
                        </td>

                        <td class="px-6 py-4 text-slate-600">
                            {{ $invoice->invoice_date->format('d.m.Y') }}
                        </td>

                        <td class="px-6 py-4 text-right text-slate-600">
                            {{ number_format((float) $invoice->total_gross, 2, ',', '.') }} €
                        </td>

                        <td class="px-6 py-4 text-center">
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
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-6 py-10 text-center text-sm text-slate-500">
                            Keine Rechnungen gefunden.
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

    </section>

</div>
