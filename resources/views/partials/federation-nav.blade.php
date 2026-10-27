<div class="flex gap-1 overflow-x-auto border-b border-slate-200">

    <a
        href="{{ route('federation.clubs') }}"
        wire:navigate
        class="whitespace-nowrap border-b-2 px-4 py-2.5 text-sm font-medium transition
        {{ request()->routeIs('federation.clubs')
            ? 'border-slate-900 text-slate-900'
            : 'border-transparent text-slate-500 hover:text-slate-800' }}"
    >
        Mitgliedsvereine
    </a>

    <a
        href="{{ route('federation.statistics') }}"
        wire:navigate
        class="whitespace-nowrap border-b-2 px-4 py-2.5 text-sm font-medium transition
        {{ request()->routeIs('federation.statistics')
            ? 'border-slate-900 text-slate-900'
            : 'border-transparent text-slate-500 hover:text-slate-800' }}"
    >
        Statistik
    </a>

    <a
        href="{{ route('federation.messages') }}"
        wire:navigate
        class="whitespace-nowrap border-b-2 px-4 py-2.5 text-sm font-medium transition
        {{ request()->routeIs('federation.messages')
            ? 'border-slate-900 text-slate-900'
            : 'border-transparent text-slate-500 hover:text-slate-800' }}"
    >
        Mitteilungen
    </a>

    <a
        href="{{ route('federation.documents') }}"
        wire:navigate
        class="whitespace-nowrap border-b-2 px-4 py-2.5 text-sm font-medium transition
        {{ request()->routeIs('federation.documents')
            ? 'border-slate-900 text-slate-900'
            : 'border-transparent text-slate-500 hover:text-slate-800' }}"
    >
        Dokumente
    </a>

</div>
