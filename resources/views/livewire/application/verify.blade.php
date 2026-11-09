<div class="mx-auto w-full max-w-md">

    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white px-6 py-10 text-center shadow-xl sm:px-8">

        @php
            $clubLogoUrl = app(\App\Services\ClubBranding::class)->logoUrl();
        @endphp

        @if($clubLogoUrl)
            <img src="{{ $clubLogoUrl }}" alt="{{ $clubName }}" class="mx-auto mb-5 max-h-28 w-auto">
        @endif

        @if($confirmed)
            <h1 class="text-xl font-bold text-slate-900">Vielen Dank!</h1>
            <p class="mt-2 text-slate-600">
                Deine Anmeldung ist bestätigt und wurde an den Verein weitergeleitet. Wir melden uns bei dir.
            </p>
        @elseif($invalid)
            <h1 class="text-xl font-bold text-slate-900">Link ungültig</h1>
            <p class="mt-2 text-slate-600">
                Dieser Link ist abgelaufen oder wurde bereits verwendet. Wenn deine Anmeldung noch nicht bestätigt ist,
                melde dich bitte erneut an.
            </p>
            @if(app(\App\Services\MemberApplicationSettings::class)->isEnabled())
                <a href="{{ route('application.form') }}" class="mt-5 inline-block rounded-lg bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white">Zur Anmeldung</a>
            @endif
        @else
            <h1 class="text-xl font-bold text-slate-900">Anmeldung bestätigen</h1>
            <p class="mt-2 text-slate-600">
                Bitte bestätige deine Anmeldung als Mitglied bei „{{ $clubName }}“.
            </p>
            <button
                type="button"
                wire:click="confirm"
                wire:loading.attr="disabled"
                class="mt-6 w-full rounded-lg bg-slate-900 px-4 py-3 font-semibold text-white disabled:opacity-60"
            >
                Anmeldung bestätigen
            </button>
        @endif

    </div>

</div>
