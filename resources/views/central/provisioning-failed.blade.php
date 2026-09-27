@component('layouts.central', ['title' => 'Einrichtung fehlgeschlagen | VEMA'])
    <div class="mx-auto w-full max-w-md overflow-hidden rounded-2xl border border-slate-200 bg-white px-6 py-8 text-center shadow-xl sm:px-8">

        <h1 class="text-2xl font-bold tracking-tight text-slate-900">
            Das hat leider nicht geklappt
        </h1>

        <p class="mt-3 text-sm text-slate-600">
            Deine E-Mail-Adresse ist bestätigt, bei der Einrichtung von
            <span class="font-medium text-slate-900">{{ $tenant->name }}</span>
            ist aber ein Fehler aufgetreten. Wir wurden benachrichtigt und melden uns bei dir,
            sobald euer Verein bereit ist.
        </p>

    </div>
@endcomponent
