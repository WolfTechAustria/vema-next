{{-- Schematische Vorschau einer Homepage-Vorlage für die Auswahl in VEMA. --}}
<span class="block overflow-hidden rounded-lg border border-slate-200 bg-white" aria-hidden="true">
    @if($templateKey === 'klassisch')
        <span class="block border-b border-slate-200 px-3 py-2 text-center">
            <span class="mx-auto block h-2 w-16 rounded" style="background: {{ $color }}"></span>
            <span class="mx-auto mt-1.5 flex justify-center gap-1.5">
                <span class="h-1 w-6 rounded bg-slate-300"></span><span class="h-1 w-6 rounded bg-slate-300"></span><span class="h-1 w-6 rounded bg-slate-300"></span>
            </span>
        </span>
        <span class="block h-10 bg-slate-100"></span>
        <span class="block space-y-1.5 p-3">
            @foreach(range(1, 3) as $row)
                <span class="flex gap-2"><span class="h-5 w-7 rounded bg-slate-200"></span><span class="mt-1 h-1.5 flex-1 rounded bg-slate-200"></span></span>
            @endforeach
        </span>
    @elseif($templateKey === 'modern')
        <span class="flex items-center justify-between px-3 py-2">
            <span class="h-2 w-10 rounded" style="background: {{ $color }}"></span>
            <span class="flex gap-1"><span class="h-1 w-5 rounded bg-slate-300"></span><span class="h-1 w-5 rounded bg-slate-300"></span></span>
        </span>
        <span class="block h-14" style="background: linear-gradient(135deg, {{ $color }}, #e2e8f0)"></span>
        <span class="grid grid-cols-3 gap-1.5 p-3">
            @foreach(range(1, 3) as $card)
                <span class="block rounded border border-slate-200"><span class="block h-5 bg-slate-200"></span><span class="m-1 block h-1 rounded bg-slate-200"></span></span>
            @endforeach
        </span>
    @elseif($templateKey === 'verein')
        <span class="flex items-center justify-between border-b border-slate-200 px-3 py-2">
            <span class="flex items-center gap-1.5"><span class="h-3 w-3 rounded-full" style="background: {{ $color }}"></span><span class="h-1.5 w-8 rounded bg-slate-300"></span></span>
            <span class="flex gap-1"><span class="h-1 w-4 rounded bg-slate-300"></span><span class="h-1 w-4 rounded" style="background: {{ $accent ?? '#d7261e' }}"></span><span class="h-1 w-4 rounded bg-slate-300"></span></span>
        </span>
        <span class="block h-11" style="background: linear-gradient(100deg, color-mix(in oklab, {{ $color }} 35%, black), color-mix(in oklab, {{ $color }} 55%, black) 60%, #cbd5e1)"></span>
        <span class="grid grid-cols-3 gap-1.5 bg-slate-100 px-3 py-2">
            <span class="block h-4 rounded-sm border-t-2 bg-white" style="border-color: {{ $color }}"></span>
            <span class="block h-4 rounded-sm border-t-2 bg-white" style="border-color: {{ $accent ?? '#d7261e' }}"></span>
            <span class="block h-4 rounded-sm border-t-2 bg-white" style="border-color: color-mix(in oklab, {{ $color }} 55%, black)"></span>
        </span>
        <span class="block h-3" style="background: color-mix(in oklab, {{ $color }} 35%, black)"></span>
    @else
        <span class="flex items-center justify-between bg-slate-900 px-3 py-2">
            <span class="h-2 w-10 rounded" style="background: {{ $color }}"></span>
            <span class="flex gap-1"><span class="h-1 w-5 rounded bg-slate-500"></span><span class="h-1 w-5 rounded bg-slate-500"></span></span>
        </span>
        <span class="block h-10 bg-slate-800"><span class="ml-3 block h-full w-1/3 -skew-x-12" style="background: {{ $color }}"></span></span>
        <span class="grid grid-cols-2 gap-1.5 p-3">
            @foreach(range(1, 2) as $tile)
                <span class="block h-9 rounded bg-slate-300"></span>
            @endforeach
        </span>
    @endif
</span>
