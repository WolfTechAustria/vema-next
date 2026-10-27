<div class="space-y-6">

    @include('partials.flash-messages')

    @include('partials.federation-nav')

    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h2 class="text-2xl font-bold tracking-tight">
                Statistik
            </h2>

            <p class="mt-1 text-sm text-slate-500">
                Kennzahlen der Mitgliedsvereine, nachts aktualisiert. Es werden nur Anzahlen übermittelt, keine Namen oder Kontaktdaten.
            </p>
        </div>

        @if($rows->isNotEmpty())
            <button type="button" wire:click="export" class="shrink-0 rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                Excel herunterladen
            </button>
        @endif
    </div>

    <dl class="grid gap-3 sm:grid-cols-4">
        @foreach([
            'Mitgliedsvereine' => $rows->count(),
            'Aktive Mitglieder' => $totals['members'],
            'Eintritte '.now()->year => $totals['joined_this_year'],
            'Austritte '.now()->year => $totals['left_this_year'],
        ] as $label => $value)
            <div class="rounded-xl border border-slate-200 bg-white px-4 py-3 shadow-sm">
                <dt class="text-sm text-slate-500">{{ $label }}</dt>
                <dd class="mt-1 text-2xl font-bold text-slate-900">{{ number_format($value, 0, ',', '.') }}</dd>
            </div>
        @endforeach
    </dl>

    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-3 text-left">Verein</th>
                    @foreach($columns as $label)
                        <th class="px-3 py-3 text-right">{{ $label }}</th>
                    @endforeach
                </tr>
            </thead>

            <tbody class="divide-y divide-slate-100">
                @forelse($rows as $row)
                    <tr wire:key="club-{{ $row['club']->id }}">
                        <td class="whitespace-nowrap px-4 py-2">
                            <div class="font-medium text-slate-900">{{ $row['club']->name }}</div>
                            <div class="text-xs text-slate-500">
                                {{ $row['statistic'] ? 'Stand '.$row['statistic']->collected_at->format('d.m.Y H:i') : 'noch kein Stand' }}
                            </div>
                        </td>
                        @foreach(array_keys($columns) as $key)
                            <td class="px-3 py-2 text-right tabular-nums">{{ $row['statistic']?->data[$key] ?? '–' }}</td>
                        @endforeach
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ count($columns) + 1 }}" class="px-4 py-8 text-center text-slate-500">
                            Noch keine Mitgliedsvereine. Ladet Vereine unter „Mitgliedsvereine“ ein.
                        </td>
                    </tr>
                @endforelse
            </tbody>

            @if($rows->isNotEmpty())
                <tfoot class="bg-slate-50 font-semibold">
                    <tr>
                        <td class="px-4 py-2">Summe</td>
                        @foreach(array_keys($columns) as $key)
                            <td class="px-3 py-2 text-right tabular-nums">{{ $totals[$key] }}</td>
                        @endforeach
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>

</div>
