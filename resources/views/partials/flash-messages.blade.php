{{--
    Gemeinsamer Meldungs-Baustein (Erfolg/Fehler/Warnung).

    Wird per @include in den einzelnen Livewire-Komponenten eingebunden statt
    im äußeren Layout: das äußere Layout (layouts.app) wird nur beim vollen
    Seitenaufruf gerendert, nicht bei einer Livewire-AJAX-Aktion — Meldungen
    dort würden also erst nach der nächsten Seitennavigation sichtbar. Durch
    die Einbindung in jeder Komponente bleibt die Meldung reaktiv (erscheint
    sofort nach der jeweiligen Aktion), ist aber per "fixed"-Positionierung
    optisch oben auf der Seite verankert, knapp unter der Kopfzeile, und
    blendet sich nach 5 Sekunden automatisch aus.
--}}
@if(session('success') || session('error') || session('warning'))

    <div
        x-data="{ show: true }"
        x-show="show"
        x-init="setTimeout(() =&gt; show = false, 5000)"
        x-transition.opacity.duration.300ms
        class="fixed left-1/2 top-20 z-40 w-full max-w-lg -translate-x-1/2 space-y-2 px-4"
    >

        @if(session('success'))
            <div class="rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm font-medium text-green-800 shadow-lg">
                {{ session('success') }}
            </div>
        @endif

        @if(session('error'))
            <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-800 shadow-lg">
                {{ session('error') }}
            </div>
        @endif

        @if(session('warning'))
            <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-medium text-amber-800 shadow-lg">
                ⚠ {{ session('warning') }}
            </div>
        @endif

    </div>

@endif
