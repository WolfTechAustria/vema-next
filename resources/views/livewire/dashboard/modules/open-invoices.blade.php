<div class="grid grid-cols-2 gap-4">
    <div>
        <div class="text-sm text-slate-500">Offen</div>
        <div class="mt-1 text-3xl font-bold text-slate-900">{{ $data['count'] }}</div>
        <div class="text-sm text-slate-500">{{ number_format($data['sum'], 2, ',', '.') }} €</div>
    </div>

    <div>
        <div class="text-sm text-slate-500">Überfällig</div>
        <div class="mt-1 text-3xl font-bold {{ $data['overdue'] > 0 ? 'text-red-700' : 'text-slate-400' }}">{{ $data['overdue'] }}</div>
    </div>
</div>

<div class="mt-4 flex flex-wrap gap-x-4 gap-y-1">
    <a href="{{ route('invoices.index', ['statusFilter' => 'sent']) }}" wire:navigate class="text-sm font-medium text-slate-700 hover:text-slate-900">
        Offene Rechnungen →
    </a>

    @if($data['overdue'] > 0)
        <a href="{{ route('invoices.index', ['statusFilter' => 'overdue']) }}" wire:navigate class="text-sm font-medium text-red-700 hover:text-red-900">
            Überfällige ansehen →
        </a>
    @endif
</div>
