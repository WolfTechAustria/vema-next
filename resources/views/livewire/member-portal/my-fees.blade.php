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
