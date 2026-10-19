<div class="space-y-3">
    @forelse($data['duties'] as $duty)
        @php
            $isUnderstaffed = $duty->assignments_count < $duty->required_helpers;
        @endphp

        <div wire:key="dashboard-duty-{{ $duty->eventID }}" class="flex items-center justify-between gap-3 text-sm">
            <div class="min-w-0">
                <div class="truncate font-medium text-slate-900">{{ $duty->role?->name ?? $duty->duty_name }}</div>
                <div class="text-slate-500">{{ $duty->duty_date->format('d.m.Y') }}</div>
            </div>

            <span class="shrink-0 rounded-full px-2 py-0.5 text-xs font-medium {{ $isUnderstaffed ? 'bg-amber-50 text-amber-800' : 'bg-emerald-50 text-emerald-700' }}">
                {{ $duty->assignments_count }}/{{ $duty->required_helpers }} besetzt
            </span>
        </div>
    @empty
        <p class="text-sm text-slate-500">Keine bevorstehenden Dienste.</p>
    @endforelse
</div>

<a href="{{ route('duty-plan.index') }}" wire:navigate class="mt-4 inline-block text-sm font-medium text-slate-700 hover:text-slate-900">
    Zum Dienstplan →
</a>
