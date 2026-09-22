<div class="flex gap-1 overflow-x-auto border-b border-slate-200">

    <a
        href="{{ route('invoices.index') }}"
        wire:navigate
        class="whitespace-nowrap border-b-2 px-4 py-2.5 text-sm font-medium transition
        {{ request()->routeIs('invoices.*')
            ? 'border-slate-900 text-slate-900'
            : 'border-transparent text-slate-500 hover:text-slate-800' }}"
    >
        Rechnungen
    </a>

    <a
        href="{{ route('invoice-recipients.index') }}"
        wire:navigate
        class="whitespace-nowrap border-b-2 px-4 py-2.5 text-sm font-medium transition
        {{ request()->routeIs('invoice-recipients.*')
            ? 'border-slate-900 text-slate-900'
            : 'border-transparent text-slate-500 hover:text-slate-800' }}"
    >
        Empfänger
    </a>

    <a
        href="{{ route('invoice-articles.index') }}"
        wire:navigate
        class="whitespace-nowrap border-b-2 px-4 py-2.5 text-sm font-medium transition
        {{ request()->routeIs('invoice-articles.*')
            ? 'border-slate-900 text-slate-900'
            : 'border-transparent text-slate-500 hover:text-slate-800' }}"
    >
        Artikel
    </a>

</div>
