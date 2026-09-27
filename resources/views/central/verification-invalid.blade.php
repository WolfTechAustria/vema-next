@component('layouts.central', ['title' => 'Link ungültig | VEMA'])
    <div class="mx-auto w-full max-w-md overflow-hidden rounded-2xl border border-slate-200 bg-white px-6 py-8 text-center shadow-xl sm:px-8">

        <h1 class="text-2xl font-bold tracking-tight text-slate-900">
            Link ungültig oder abgelaufen
        </h1>

        <p class="mt-3 text-sm text-slate-600">
            Dieser Bestätigungslink wurde bereits verwendet oder ist älter als
            {{ config('tenancy.verification_hours') }} Stunden.
        </p>

        <div class="mt-6 flex flex-col gap-3">
            <a href="{{ route('central.find-club') }}" class="rounded-lg bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-800">
                Zu meinem Verein
            </a>
            <a href="{{ route('central.register') }}" class="rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                Neu registrieren
            </a>
        </div>

    </div>
@endcomponent
