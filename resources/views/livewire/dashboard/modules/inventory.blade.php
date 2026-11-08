<div class="grid grid-cols-3 gap-4">
    <div>
        <div class="text-sm text-slate-500">Im Bestand</div>
        <div class="mt-1 text-3xl font-bold text-slate-900">{{ $data['items'] }}</div>
    </div>

    <div>
        <div class="text-sm text-slate-500">Ausgegeben</div>
        <div class="mt-1 text-3xl font-bold text-slate-900">{{ $data['issued'] }}</div>
    </div>

    <div>
        <div class="text-sm text-slate-500">Rückgabe überfällig</div>
        <div class="mt-1 text-3xl font-bold {{ $data['overdueReturns'] > 0 ? 'text-red-700' : 'text-slate-400' }}">{{ $data['overdueReturns'] }}</div>
    </div>
</div>

@if($data['dueInspections']->isNotEmpty())
    <div class="mt-4">
        <div class="mb-1 text-xs font-semibold uppercase tracking-wide text-slate-500">Prüfung fällig</div>
        <ul class="divide-y divide-slate-100 text-sm">
            @foreach($data['dueInspections'] as $item)
                <li class="flex justify-between gap-2 py-1.5">
                    <a href="{{ route('inventory.show', $item) }}" wire:navigate class="truncate text-slate-700 hover:underline">{{ $item->label() }}</a>
                    <span class="{{ $item->isInspectionOverdue() ? 'font-semibold text-red-700' : 'text-amber-700' }}">{{ $item->next_inspection_on->format('d.m.Y') }}</span>
                </li>
            @endforeach
        </ul>
    </div>
@endif

<div class="mt-4 flex flex-wrap gap-x-4 gap-y-1">
    <a href="{{ route('inventory.index') }}" wire:navigate class="text-sm font-medium text-slate-700 hover:text-slate-900">
        Material →
    </a>

    @if($data['overdueReturns'] > 0)
        <a href="{{ route('inventory.index', ['statusFilter' => 'overdue_return']) }}" wire:navigate class="text-sm font-medium text-red-700 hover:text-red-900">
            Überfällige Rückgaben →
        </a>
    @endif
</div>
