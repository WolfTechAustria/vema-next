<div class="space-y-6">

    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <a href="{{ route('events.index') }}" wire:navigate class="text-sm font-medium text-slate-500 hover:text-slate-800">
                ← Alle Termine
            </a>

            <h2 class="mt-1 text-2xl font-bold tracking-tight">Anwesenheit {{ $year }}</h2>

            <p class="mt-1 text-sm text-slate-500">
                Gezählt werden alle Termine, für die eine Anwesenheit erfasst wurde.
            </p>
        </div>

        <select wire:model.live="year" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
            @foreach($years as $selectableYear)
                <option value="{{ $selectableYear }}">{{ $selectableYear }}</option>
            @endforeach
        </select>
    </div>

    <div class="grid gap-6 lg:grid-cols-[2fr_1fr]">

        <section class="h-fit rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-6 py-4">
                <h3 class="font-semibold text-slate-900">Mitglieder</h3>
            </div>

            <div class="divide-y divide-slate-100">
                @forelse($rows as $row)
                    <div wire:key="attendance-row-{{ $row['member']->memberID }}" class="flex items-center gap-4 px-6 py-3">
                        <a href="{{ route('members.show', $row['member']) }}" wire:navigate class="min-w-0 flex-1 truncate text-sm font-medium text-slate-900 hover:underline">
                            {{ $row['member']->full_name }}
                        </a>

                        <div class="hidden h-2 w-32 overflow-hidden rounded-full bg-slate-100 sm:block">
                            <div class="h-full rounded-full {{ $row['percent'] >= 75 ? 'bg-emerald-500' : ($row['percent'] >= 50 ? 'bg-amber-400' : 'bg-red-400') }}" style="width: {{ $row['percent'] }}%"></div>
                        </div>

                        <div class="w-28 text-right text-sm text-slate-600">
                            {{ $row['present'] }} / {{ $row['total'] }}
                            <span class="ml-1 font-semibold text-slate-900">{{ $row['percent'] }} %</span>
                        </div>
                    </div>
                @empty
                    <p class="px-6 py-4 text-sm text-slate-500">Für {{ $year }} wurde noch keine Anwesenheit erfasst.</p>
                @endforelse
            </div>
        </section>

        <section class="h-fit rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-6 py-4">
                <h3 class="font-semibold text-slate-900">Erfasste Termine ({{ $events->count() }})</h3>
            </div>

            <div class="divide-y divide-slate-100">
                @forelse($events as $event)
                    <a wire:key="attendance-event-{{ $event->eventID }}" href="{{ route('events.attendance', $event) }}" wire:navigate class="flex items-center justify-between gap-3 px-6 py-3 hover:bg-slate-50">
                        <div class="min-w-0">
                            <div class="truncate text-sm font-medium text-slate-900">{{ $event->title }}</div>
                            <div class="text-xs text-slate-500">{{ $event->starts_at->format('d.m.Y') }}</div>
                        </div>
                        <div class="shrink-0 text-sm text-slate-600">{{ $event->present_count }} / {{ $event->attendances_count }}</div>
                    </a>
                @empty
                    <p class="px-6 py-4 text-sm text-slate-500">Keine erfassten Termine.</p>
                @endforelse
            </div>
        </section>

    </div>

</div>
