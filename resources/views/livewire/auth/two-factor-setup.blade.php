<div class="mx-auto max-w-2xl space-y-6">

    @include('partials.flash-messages')

    @if($isRequired && ! $isEnabled)
        <div class="rounded-xl border border-amber-200 bg-amber-50 px-5 py-4 text-sm text-amber-800">
            Dein Verein verlangt für alle Funktionäre eine <strong>Zwei-Faktor-Anmeldung</strong>.
            Richte sie bitte jetzt ein – danach kannst du VEMA wie gewohnt nutzen.
        </div>
    @endif

    <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">

        @if($step === 'overview')
            <h3 class="font-semibold text-slate-900">Zwei-Faktor-Anmeldung ist aktiv</h3>

            <p class="mt-2 text-sm text-slate-600">
                Methode: <strong>{{ $methodLabels[$user->two_factor_method] ?? '–' }}</strong>
                · eingerichtet am {{ $user->two_factor_confirmed_at->format('d.m.Y') }}
                · {{ $remainingRecoveryCodes }} Wiederherstellungscodes übrig
            </p>

            <div class="mt-5 flex flex-wrap gap-2">
                <button type="button" wire:click="startSetup" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                    Methode wechseln
                </button>

                <button
                    type="button"
                    wire:click="regenerateRecoveryCodes"
                    wire:confirm="Neue Wiederherstellungscodes erzeugen? Die bisherigen werden ungültig."
                    class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
                >
                    Neue Wiederherstellungscodes
                </button>

                @unless($isRequired)
                    <button
                        type="button"
                        wire:click="disable"
                        wire:confirm="Zwei-Faktor-Anmeldung ausschalten?"
                        class="rounded-lg border border-red-200 px-4 py-2 text-sm font-medium text-red-700 hover:bg-red-50"
                    >
                        Ausschalten
                    </button>
                @endunless
            </div>
        @endif

        @if($step === 'choose')
            <h3 class="font-semibold text-slate-900">Wie möchtest du dich zusätzlich bestätigen?</h3>

            <p class="mt-1 text-sm text-slate-500">
                Nach dem Passwort fragt VEMA einen sechsstelligen Code ab.
            </p>

            <div class="mt-4 space-y-3">
                <label class="flex cursor-pointer gap-3 rounded-lg border border-slate-200 p-4 hover:bg-slate-50">
                    <input type="radio" wire:model="method" value="app" class="mt-1">
                    <span>
                        <span class="block text-sm font-semibold text-slate-900">Authenticator-App (empfohlen)</span>
                        <span class="block text-sm text-slate-500">z. B. Google Authenticator, Microsoft Authenticator – funktioniert auch ohne Internet.</span>
                    </span>
                </label>

                <label class="flex cursor-pointer gap-3 rounded-lg border border-slate-200 p-4 hover:bg-slate-50">
                    <input type="radio" wire:model="method" value="email" class="mt-1">
                    <span>
                        <span class="block text-sm font-semibold text-slate-900">Code per E-Mail</span>
                        <span class="block text-sm text-slate-500">Bei jeder Anmeldung kommt ein Code an {{ $user->email }}.</span>
                    </span>
                </label>
            </div>

            <div class="mt-5 flex gap-2">
                <button type="button" wire:click="chooseMethod" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">
                    Weiter
                </button>

                @if($isEnabled)
                    <button type="button" wire:click="$set('step', 'overview')" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                        Abbrechen
                    </button>
                @endif
            </div>
        @endif

        @if($step === 'confirm')
            @if($secret)
                <h3 class="font-semibold text-slate-900">Authenticator-App verbinden</h3>

                <ol class="mt-2 list-decimal space-y-1 pl-5 text-sm text-slate-600">
                    <li>Öffne deine Authenticator-App und füge ein neues Konto hinzu.</li>
                    <li>Scanne den QR-Code oder tippe den Schlüssel ab.</li>
                    <li>Gib den sechsstelligen Code aus der App unten ein.</li>
                </ol>

                <div class="mt-4 flex flex-col items-center gap-3 sm:flex-row sm:items-start">
                    <div class="rounded-lg border border-slate-200 bg-white p-2">{!! $qrCodeSvg !!}</div>

                    <div class="text-sm">
                        <div class="text-slate-500">Schlüssel</div>
                        <code class="mt-1 block break-all rounded bg-slate-100 px-2 py-1 font-mono text-slate-900">{{ trim(chunk_split($secret, 4, ' ')) }}</code>
                    </div>
                </div>
            @else
                <h3 class="font-semibold text-slate-900">E-Mail-Adresse bestätigen</h3>

                <p class="mt-2 text-sm text-slate-600">
                    Wir haben einen Code an <strong>{{ $user->email }}</strong> geschickt.
                    <button type="button" wire:click="resendEmailCode" class="font-medium text-slate-700 underline">Neuen Code senden</button>
                </p>
            @endif

            <div class="mt-5 max-w-xs">
                <label for="two-factor-code" class="mb-1 block text-sm font-medium text-slate-700">Code</label>
                <input
                    id="two-factor-code"
                    type="text"
                    wire:model="code"
                    wire:keydown.enter="confirm"
                    inputmode="numeric"
                    autocomplete="one-time-code"
                    class="w-full rounded-lg border border-slate-300 px-3 py-2 text-center text-lg tracking-widest"
                >
                @error('code') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="mt-5 flex gap-2">
                <button type="button" wire:click="confirm" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">
                    Bestätigen
                </button>
                <button type="button" wire:click="startSetup" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                    Zurück
                </button>
            </div>
        @endif

        @if($step === 'codes')
            <h3 class="font-semibold text-slate-900">Deine Wiederherstellungscodes</h3>

            <p class="mt-2 text-sm text-slate-600">
                Bewahre diese Codes sicher auf (z. B. ausdrucken). Hast du keinen Zugriff auf deine App oder E-Mail,
                meldest du dich mit einem dieser Codes an. Jeder Code gilt nur einmal. <strong>Sie werden nur jetzt angezeigt.</strong>
            </p>

            <div class="mt-4 grid grid-cols-2 gap-2 rounded-lg bg-slate-50 p-4 font-mono text-sm text-slate-900">
                @foreach($recoveryCodes as $recoveryCode)
                    <div>{{ $recoveryCode }}</div>
                @endforeach
            </div>

            <button type="button" wire:click="finish" class="mt-5 rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">
                Codes gesichert – fertig
            </button>
        @endif

    </section>

</div>
