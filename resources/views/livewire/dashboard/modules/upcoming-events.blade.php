<div class="space-y-3">
    @forelse($data['events'] as $event)
        <div wire:key="dashboard-event-{{ $event->eventID }}" class="text-sm">
            <div class="font-medium text-slate-900">{{ $event->title }}</div>
            <div class="text-slate-500">
                {{ $event->formattedPeriod() }}
                @if($event->location)
                    · {{ $event->location }}
                @endif
            </div>
        </div>
    @empty
        <p class="text-sm text-slate-500">Keine bevorstehenden Termine.</p>
    @endforelse
</div>

<a href="{{ route('events.index') }}" wire:navigate class="mt-4 inline-block text-sm font-medium text-slate-700 hover:text-slate-900">
    Alle Termine →
</a>
