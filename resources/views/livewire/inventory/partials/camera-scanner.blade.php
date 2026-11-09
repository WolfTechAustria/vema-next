{{-- Kamera-Scan; $onCode ist ein JS-Ausdruck mit der Variable "code", $label der Button-Text, $once schließt nach dem ersten Treffer. --}}
<div x-data="barcodeScanner((code) => { {{ $onCode }} }, { once: {{ ($once ?? false) ? 'true' : 'false' }} })" wire:ignore class="{{ $class ?? '' }}">
    <button
        type="button"
        x-show="! open"
        x-on:click="start()"
        class="inline-flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
    >
        <span aria-hidden="true">▣</span>
        {{ $label ?? 'Kamera' }}
    </button>

    <p x-show="error" x-text="error" class="mt-1 text-xs text-red-600"></p>

    <div x-show="open" x-cloak class="mt-2 space-y-2">
        <div class="relative overflow-hidden rounded-lg bg-black">
            <video x-ref="video" playsinline muted class="aspect-[4/3] w-full object-cover"></video>
            <div class="pointer-events-none absolute inset-x-8 top-1/2 h-0.5 -translate-y-1/2 bg-red-500/70"></div>
            <div x-show="starting" class="absolute inset-0 flex items-center justify-center text-sm text-white">Kamera wird gestartet …</div>
        </div>

        <button type="button" x-on:click="stop()" class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm">Kamera schließen</button>
    </div>
</div>
