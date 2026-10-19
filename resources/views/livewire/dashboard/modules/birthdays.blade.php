<div class="space-y-2">
    @forelse($data['birthdays'] as $birthday)
        <div wire:key="dashboard-birthday-{{ $birthday['member']->memberID }}" class="flex items-center justify-between gap-3 text-sm">
            <a href="{{ route('members.show', $birthday['member']) }}" wire:navigate class="min-w-0 truncate font-medium text-slate-900 hover:underline">
                {{ $birthday['member']->full_name }}
            </a>

            <span class="shrink-0 text-slate-500">
                {{ $birthday['date']->isToday() ? 'heute' : $birthday['date']->format('d.m.') }}
                ·
                <span class="{{ $birthday['isRound'] ? 'rounded-full bg-amber-100 px-2 py-0.5 font-semibold text-amber-800' : '' }}">
                    {{ $birthday['age'] }}
                </span>
            </span>
        </div>
    @empty
        <p class="text-sm text-slate-500">Keine Geburtstage in den nächsten 30 Tagen.</p>
    @endforelse
</div>

<a href="{{ route('members.birthdays') }}" wire:navigate class="mt-4 inline-block text-sm font-medium text-slate-700 hover:text-slate-900">
    Geburtstagsliste →
</a>
