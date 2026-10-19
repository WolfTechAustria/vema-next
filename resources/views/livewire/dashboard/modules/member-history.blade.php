@php
    $years = $data['years'];
    $maxStock = max(1, max(array_column($years, 'stock') ?: [0]));
    $barWidth = 100 / max(1, count($years));
@endphp

<div class="mb-4 flex items-center justify-between gap-3">
    <p class="text-sm text-slate-500">
        Mitgliederstand zum Jahresende ({{ now()->year }}: heute)
    </p>

    <div class="flex shrink-0 rounded-lg border border-slate-200 p-0.5 text-xs font-medium">
        @foreach(\App\Livewire\Dashboard\Index::HISTORY_RANGES as $range)
            <button
                type="button"
                wire:click="setHistoryYears({{ $range }})"
                class="rounded-md px-2.5 py-1 {{ $historyYears === $range ? 'bg-slate-900 text-white' : 'text-slate-600 hover:bg-slate-100' }}"
            >
                {{ $range }} J.
            </button>
        @endforeach
    </div>
</div>

<div aria-hidden="true">
    <svg viewBox="0 0 100 40" preserveAspectRatio="none" class="h-40 w-full">
        @foreach($years as $index => $year)
            @php
                $height = $year['stock'] / $maxStock * 36;
            @endphp

            <rect
                x="{{ $index * $barWidth + $barWidth * 0.15 }}"
                y="{{ 40 - $height }}"
                width="{{ $barWidth * 0.7 }}"
                height="{{ $height }}"
                rx="0.6"
                class="{{ $loop->last ? 'fill-slate-900' : 'fill-slate-300' }}"
            >
                <title>{{ $year['year'] }}: {{ $year['stock'] }} Mitglieder</title>
            </rect>
        @endforeach
    </svg>

    <div class="mt-2 grid text-center text-[11px] leading-tight" style="grid-template-columns: repeat({{ count($years) }}, minmax(0, 1fr));">
        @foreach($years as $year)
            <div>
                <div class="font-semibold text-slate-900">{{ $year['stock'] }}</div>
                <div class="text-slate-500">{{ count($years) > 10 ? substr($year['year'], 2) : $year['year'] }}</div>
                @if(count($years) <= 10)
                    <div class="mt-0.5 whitespace-nowrap">
                        <span class="text-emerald-700">+{{ $year['joined'] }}</span>
                        <span class="text-red-700">−{{ $year['left'] }}</span>
                    </div>
                @endif
            </div>
        @endforeach
    </div>
</div>

<table class="sr-only">
    <caption>Mitgliederentwicklung der letzten {{ count($years) }} Jahre</caption>
    <thead>
        <tr><th>Jahr</th><th>Bestand</th><th>Eintritte</th><th>Austritte</th></tr>
    </thead>
    <tbody>
        @foreach($years as $year)
            <tr><td>{{ $year['year'] }}</td><td>{{ $year['stock'] }}</td><td>{{ $year['joined'] }}</td><td>{{ $year['left'] }}</td></tr>
        @endforeach
    </tbody>
</table>
