<div class="space-y-6">

    @include('partials.flash-messages')

    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <a href="{{ route('events.index') }}" wire:navigate class="text-sm font-medium text-slate-500 hover:text-slate-800">
                ← Alle Termine
            </a>

            <h2 class="mt-1 text-2xl font-bold tracking-tight">{{ $event->title }}</h2>

            <div class="mt-1 text-sm text-slate-500">
                {{ $event->formattedPeriod() }}
                @if($event->location)
                    · {{ $event->location }}
                @endif
            </div>
        </div>

        <a href="{{ route('events.attendance-statistics', ['year' => $event->starts_at->year]) }}" wire:navigate class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
            Statistik {{ $event->starts_at->year }}
        </a>
    </div>

    <section class="rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-col gap-3 border-b border-slate-200 px-6 py-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h3 class="font-semibold text-slate-900">Anwesenheitsliste</h3>
                <p class="text-sm text-slate-500">
                    @if($event->attendance_taken_at)
                        Erfasst am {{ $event->attendance_taken_at->format('d.m.Y') }} – Änderungen sind jederzeit möglich.
                    @else
                        Noch nicht erfasst. Vorausgewählt sind alle, die zugesagt haben.
                    @endif
                </p>
            </div>

            <div class="text-sm font-semibold text-slate-900">
                {{ $presentCount }} von {{ $totalCount }} anwesend
            </div>
        </div>

        <div class="flex flex-col gap-3 border-b border-slate-100 px-6 py-3 sm:flex-row sm:items-center sm:justify-between">
            <input
                type="search"
                wire:model.live.debounce.300ms="search"
                placeholder="Mitglied suchen …"
                class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm sm:max-w-xs"
            >

            <div class="flex gap-4 text-sm">
                <button type="button" wire:click="markAll(true)" class="font-medium text-slate-600 hover:text-slate-900">Alle anwesend</button>
                <button type="button" wire:click="markAll(false)" class="font-medium text-slate-600 hover:text-slate-900">Keine</button>
            </div>
        </div>

        <div class="divide-y divide-slate-100">
            @forelse($members as $member)
                <label wire:key="attendance-member-{{ $member->memberID }}" class="flex cursor-pointer items-center gap-3 px-6 py-3 hover:bg-slate-50">
                    <input
                        type="checkbox"
                        wire:model.live="presentMemberIDs"
                        value="{{ $member->memberID }}"
                        class="h-5 w-5 rounded border-slate-300"
                    >
                    <span class="text-sm text-slate-900">{{ $member->full_name }}</span>
                    @unless($member->active)
                        <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-500">ausgetreten</span>
                    @endunless
                </label>
            @empty
                <p class="px-6 py-4 text-sm text-slate-500">Keine Mitglieder gefunden.</p>
            @endforelse
        </div>

        <div class="flex justify-end border-t border-slate-200 px-6 py-4">
            <button
                type="button"
                wire:click="save"
                class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800"
            >
                Anwesenheit speichern
            </button>
        </div>
    </section>

</div>
