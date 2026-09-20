<div class="flex gap-1 overflow-x-auto border-b border-slate-200">

    <a
        href="{{ route('admin.settings.index') }}"
        wire:navigate
        class="whitespace-nowrap border-b-2 px-4 py-2.5 text-sm font-medium transition
        {{ request()->routeIs('admin.settings.*')
            ? 'border-slate-900 text-slate-900'
            : 'border-transparent text-slate-500 hover:text-slate-800' }}"
    >
        Vereinseinstellungen
    </a>

    <a
        href="{{ route('admin.users.index') }}"
        wire:navigate
        class="whitespace-nowrap border-b-2 px-4 py-2.5 text-sm font-medium transition
        {{ request()->routeIs('admin.users.*')
            ? 'border-slate-900 text-slate-900'
            : 'border-transparent text-slate-500 hover:text-slate-800' }}"
    >
        Benutzerverwaltung
    </a>

    <a
        href="{{ route('admin.activity-log.index') }}"
        wire:navigate
        class="whitespace-nowrap border-b-2 px-4 py-2.5 text-sm font-medium transition
        {{ request()->routeIs('admin.activity-log.*')
            ? 'border-slate-900 text-slate-900'
            : 'border-transparent text-slate-500 hover:text-slate-800' }}"
    >
        Aktivitätsprotokoll
    </a>

</div>
