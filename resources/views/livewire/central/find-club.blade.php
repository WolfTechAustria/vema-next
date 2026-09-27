<div class="mx-auto w-full max-w-md">

    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xl">

        <div class="border-b border-slate-200 px-6 py-7 text-center sm:px-8">
            <h1 class="text-2xl font-bold tracking-tight text-slate-900">
                Zu deinem Verein
            </h1>

            <p class="mt-2 text-sm text-slate-500">
                Jeder Verein hat eine eigene Adresse, z. B. <span class="font-medium text-slate-700">musikverein.{{ config('tenancy.tenant_domain') }}</span>.
                Dort melden sich Vorstand und Mitglieder an.
            </p>
        </div>

        <form wire:submit="openClub" novalidate class="px-6 py-7 sm:px-8">
            <label for="club-address" class="mb-1.5 block text-sm font-medium text-slate-700">Adresse eures Vereins</label>

            <div class="flex items-stretch overflow-hidden rounded-lg border {{ $errors->has('address') ? 'border-red-400' : 'border-slate-300' }}">
                <input
                    id="club-address"
                    type="text"
                    wire:model="address"
                    autocapitalize="none"
                    spellcheck="false"
                    placeholder="musikverein"
                    autofocus
                    class="min-w-0 flex-1 border-0 px-3 py-2.5 text-base focus:ring-0"
                >
                <span class="flex items-center bg-slate-50 px-3 text-sm text-slate-500">.{{ config('tenancy.tenant_domain') }}</span>
            </div>

            @error('address')
                <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
            @enderror

            <button
                type="submit"
                class="mt-5 flex w-full items-center justify-center rounded-lg bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-800"
            >
                Zur Anmeldung
            </button>
        </form>

        <form wire:submit="sendLinks" novalidate class="border-t border-slate-200 bg-slate-50 px-6 py-6 sm:px-8">
            <p class="mb-3 text-sm font-medium text-slate-700">Adresse vergessen?</p>

            <div class="flex gap-2">
                <input
                    type="email"
                    inputmode="email"
                    wire:model="email"
                    autocomplete="email"
                    autocapitalize="none"
                    placeholder="Kontakt-E-Mail des Vereins"
                    class="min-w-0 flex-1 rounded-lg border px-3 py-2 text-sm {{ $errors->has('email') ? 'border-red-400' : 'border-slate-300' }}"
                >
                <button
                    type="submit"
                    wire:loading.attr="disabled"
                    wire:target="sendLinks"
                    class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100 disabled:opacity-60"
                >
                    Link senden
                </button>
            </div>

            @error('email')
                <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
            @enderror

            @if($statusMessage)
                <p class="mt-3 text-sm text-emerald-700">{{ $statusMessage }}</p>
            @endif
        </form>

    </div>

    <p class="mt-6 text-center text-sm text-slate-600">
        Noch kein Verein bei VEMA?
        <a href="{{ route('central.register') }}" class="font-medium text-slate-900 underline">Jetzt kostenlos registrieren</a>
    </p>

</div>
