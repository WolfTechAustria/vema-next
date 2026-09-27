<div class="mx-auto w-full max-w-lg">

    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xl">

        <div class="border-b border-slate-200 px-6 py-7 text-center sm:px-8">
            <h1 class="text-2xl font-bold tracking-tight text-slate-900">
                Verein registrieren
            </h1>

            <p class="mt-2 text-sm text-slate-500">
                {{ config('tenancy.trial_days') }} Tage alle Funktionen kostenlos testen — ohne Zahlungsdaten.
                Danach geht es automatisch im kostenlosen Starter-Paket weiter, sofern kein anderes Paket gebucht wird.
            </p>

            @if($plan)
                <p class="mt-3 inline-block rounded-full bg-slate-100 px-3 py-1 text-xs font-medium text-slate-700">
                    Gewähltes Paket: {{ $plan->label() }}
                </p>
            @endif
        </div>

        @if($registeredEmail)

            <div class="px-6 py-8 text-center sm:px-8">
                <p class="text-lg font-semibold text-slate-900">Fast geschafft!</p>

                <p class="mt-3 text-sm text-slate-600">
                    Wir haben einen Bestätigungslink an <strong>{{ $registeredEmail }}</strong> geschickt.
                    Sobald du ihn öffnest, wird euer Verein eingerichtet.
                </p>

                <p class="mt-3 text-xs text-slate-500">
                    Keine E-Mail bekommen? Bitte auch im Spam-Ordner nachsehen.
                </p>
            </div>

        @else

            <form wire:submit="register" novalidate class="space-y-5 px-6 py-7 sm:px-8">

                <div>
                    <label for="club-name" class="mb-1.5 block text-sm font-medium text-slate-700">Name des Vereins</label>
                    <input
                        id="club-name"
                        type="text"
                        wire:model.live.debounce.400ms="club_name"
                        autocomplete="organization"
                        placeholder="z. B. Musikverein Musterdorf"
                        autofocus
                        class="w-full rounded-lg border px-3 py-2.5 text-base {{ $errors->has('club_name') ? 'border-red-400' : 'border-slate-300' }}"
                    >
                    @error('club_name')
                        <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="club-slug" class="mb-1.5 block text-sm font-medium text-slate-700">Adresse eures Vereins</label>
                    <div class="flex items-stretch overflow-hidden rounded-lg border {{ $errors->has('slug') ? 'border-red-400' : 'border-slate-300' }}">
                        <input
                            id="club-slug"
                            type="text"
                            wire:model.live.blur="slug"
                            autocapitalize="none"
                            spellcheck="false"
                            placeholder="musikverein-musterdorf"
                            class="min-w-0 flex-1 border-0 px-3 py-2.5 text-base focus:ring-0"
                        >
                        <span class="flex items-center bg-slate-50 px-3 text-sm text-slate-500">.{{ config('tenancy.tenant_domain') }}</span>
                    </div>
                    @error('slug')
                        <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="grid gap-5 sm:grid-cols-2">
                    <div>
                        <label for="contact-name" class="mb-1.5 block text-sm font-medium text-slate-700">Dein Name</label>
                        <input
                            id="contact-name"
                            type="text"
                            wire:model="contact_name"
                            autocomplete="name"
                            class="w-full rounded-lg border px-3 py-2.5 text-base {{ $errors->has('contact_name') ? 'border-red-400' : 'border-slate-300' }}"
                        >
                        @error('contact_name')
                            <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="contact-email" class="mb-1.5 block text-sm font-medium text-slate-700">Deine E-Mail-Adresse</label>
                        <input
                            id="contact-email"
                            type="email"
                            inputmode="email"
                            wire:model="contact_email"
                            autocomplete="email"
                            autocapitalize="none"
                            spellcheck="false"
                            class="w-full rounded-lg border px-3 py-2.5 text-base {{ $errors->has('contact_email') ? 'border-red-400' : 'border-slate-300' }}"
                        >
                        @error('contact_email')
                            <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <p class="text-xs text-slate-500">
                    Mit dieser Adresse meldest du dich später als Administrator an.
                </p>

                {{-- Honeypot --}}
                <div class="hidden" aria-hidden="true">
                    <label for="website">Website</label>
                    <input id="website" type="text" wire:model="website" tabindex="-1" autocomplete="off">
                </div>

                <div>
                    <label class="flex items-start gap-3">
                        <input type="checkbox" wire:model="accept_terms" class="mt-0.5 h-4 w-4 rounded border-slate-300">
                        <span class="text-sm text-slate-700">
                            Ich akzeptiere die
                            <a href="{{ config('tenancy.website_url') }}/agb.html" target="_blank" class="font-medium underline">AGB</a>
                            und habe die
                            <a href="{{ config('tenancy.website_url') }}/datenschutz.html" target="_blank" class="font-medium underline">Datenschutzerklärung</a>
                            gelesen.
                        </span>
                    </label>
                    @error('accept_terms')
                        <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <button
                    type="submit"
                    wire:loading.attr="disabled"
                    wire:target="register"
                    class="flex w-full items-center justify-center rounded-lg bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-800 disabled:opacity-60"
                >
                    <span wire:loading.remove wire:target="register">Kostenlos registrieren</span>
                    <span wire:loading wire:target="register">Wird gesendet …</span>
                </button>

                <p class="text-center text-sm text-slate-500">
                    Schon registriert?
                    <a href="{{ route('central.find-club') }}" class="font-medium text-slate-800 underline">Zu deinem Verein</a>
                </p>

            </form>

        @endif

    </div>

</div>
