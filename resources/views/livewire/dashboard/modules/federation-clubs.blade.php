<div class="grid grid-cols-3 gap-4">

    <a href="{{ route('federation.clubs') }}" wire:navigate class="rounded-lg p-1 hover:bg-slate-50">
        <div class="text-sm text-slate-500">Vereine</div>
        <div class="mt-1 text-3xl font-bold text-slate-900">{{ $data['clubs'] }}</div>
    </a>

    <a href="{{ route('federation.statistics') }}" wire:navigate class="rounded-lg p-1 hover:bg-slate-50">
        <div class="text-sm text-slate-500">Mitglieder</div>
        <div class="mt-1 text-3xl font-bold text-slate-900">{{ number_format($data['members'], 0, ',', '.') }}</div>
    </a>

    <div class="p-1">
        <div class="text-sm text-slate-500">Eingeladen</div>
        <div class="mt-1 text-3xl font-bold text-amber-700">{{ $data['invited'] }}</div>
    </div>

</div>
