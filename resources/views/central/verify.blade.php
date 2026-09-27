@component('layouts.central', ['title' => 'Registrierung bestätigen | VEMA'])
    <div class="mx-auto w-full max-w-md overflow-hidden rounded-2xl border border-slate-200 bg-white px-6 py-8 text-center shadow-xl sm:px-8">

        <h1 class="text-2xl font-bold tracking-tight text-slate-900">
            {{ $tenant->name }}
        </h1>

        <p class="mt-3 text-sm text-slate-600">
            Mit einem Klick wird euer Verein unter
            <span class="font-medium text-slate-900">{{ $tenant->host() }}</span>
            eingerichtet. Anschließend legst du dein Passwort fest und die
            {{ config('tenancy.trial_days') }}-tägige Testphase beginnt.
        </p>

        <form
            method="POST"
            action="{{ route('central.register.verify.store', ['token' => $token]) }}"
            class="mt-6"
            onsubmit="const button = this.querySelector('button'); button.disabled = true; button.textContent = 'Wird eingerichtet … (dauert einen Moment)';"
        >
            @csrf

            <button
                type="submit"
                class="flex w-full items-center justify-center rounded-lg bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-800 disabled:opacity-60"
            >
                Verein jetzt einrichten
            </button>
        </form>

    </div>
@endcomponent
