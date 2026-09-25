<div class="flex gap-1 overflow-x-auto border-b border-slate-200">

    <a
        href="{{ route('cash-book.index') }}"
        wire:navigate
        class="whitespace-nowrap border-b-2 px-4 py-2.5 text-sm font-medium transition
        {{ request()->routeIs('cash-book.index')
            ? 'border-slate-900 text-slate-900'
            : 'border-transparent text-slate-500 hover:text-slate-800' }}"
    >
        Buchungen
    </a>

    <a
        href="{{ route('cash-book.years') }}"
        wire:navigate
        class="whitespace-nowrap border-b-2 px-4 py-2.5 text-sm font-medium transition
        {{ request()->routeIs('cash-book.years')
            ? 'border-slate-900 text-slate-900'
            : 'border-transparent text-slate-500 hover:text-slate-800' }}"
    >
        Vereinsjahre
    </a>

</div>
