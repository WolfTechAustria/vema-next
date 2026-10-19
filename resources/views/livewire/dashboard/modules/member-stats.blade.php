<div class="grid grid-cols-3 gap-4">

    <a href="{{ route('members.index') }}" wire:navigate class="rounded-lg p-1 hover:bg-slate-50">
        <div class="text-sm text-slate-500">Aktiv</div>
        <div class="mt-1 text-3xl font-bold text-slate-900">{{ $data['active'] }}</div>
    </a>

    <div class="p-1">
        <div class="text-sm text-slate-500">Eintritte {{ $data['year'] }}</div>
        <div class="mt-1 text-3xl font-bold text-emerald-700">{{ $data['joined'] }}</div>
    </div>

    <div class="p-1">
        <div class="text-sm text-slate-500">Austritte {{ $data['year'] }}</div>
        <div class="mt-1 text-3xl font-bold text-slate-600">{{ $data['left'] }}</div>
    </div>

</div>
