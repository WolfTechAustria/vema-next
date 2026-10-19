@if(! $data['year'])

    <p class="text-sm text-slate-500">Noch kein Vereinsjahr im Kassabuch angelegt.</p>

@else

    <div class="text-sm text-slate-500">{{ $data['year']->name }}</div>

    <div class="mt-1 text-3xl font-bold {{ $data['balance'] < 0 ? 'text-red-700' : 'text-slate-900' }}">
        {{ number_format($data['balance'], 2, ',', '.') }} €
    </div>

    <div class="mt-3 flex gap-6 text-sm">
        <div>
            <span class="text-slate-500">Einnahmen</span>
            <span class="font-medium text-emerald-700">{{ number_format($data['income'], 2, ',', '.') }} €</span>
        </div>
        <div>
            <span class="text-slate-500">Ausgaben</span>
            <span class="font-medium text-red-700">{{ number_format($data['expense'], 2, ',', '.') }} €</span>
        </div>
    </div>

@endif

<a href="{{ route('cash-book.index') }}" wire:navigate class="mt-4 inline-block text-sm font-medium text-slate-700 hover:text-slate-900">
    Zum Kassabuch →
</a>
