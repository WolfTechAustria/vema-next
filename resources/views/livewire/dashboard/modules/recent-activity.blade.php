<div class="divide-y divide-slate-100">
    @forelse($data['entries'] as $entry)
        <div wire:key="dashboard-activity-{{ $entry->activityLogID }}" class="flex flex-col gap-0.5 py-2 text-sm sm:flex-row sm:gap-4">
            <div class="shrink-0 text-slate-500 sm:w-32">{{ $entry->created_at->format('d.m.Y H:i') }}</div>
            <div class="shrink-0 font-medium text-slate-700 sm:w-40 sm:truncate">{{ $entry->actor_name }}</div>
            <div class="min-w-0 text-slate-900">{{ $entry->description }}</div>
        </div>
    @empty
        <p class="text-sm text-slate-500">Noch keine Einträge.</p>
    @endforelse
</div>

<a href="{{ route('admin.activity-log.index') }}" wire:navigate class="mt-4 inline-block text-sm font-medium text-slate-700 hover:text-slate-900">
    Zum Aktivitätsprotokoll →
</a>
