<div class="mx-auto w-full max-w-md">

    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xl">

        {{-- Kopf --}}
        <div class="border-b border-slate-200 px-8 py-8 text-center">

            <img
                src="{{ asset('images/logo.jpg') }}"
                alt="VEMA"
                class="mx-auto mb-5 max-h-50 w-auto"
            >

            <h1 class="text-2xl font-bold tracking-tight text-slate-900">
                Mitgliederbereich
            </h1>

            <p class="mt-2 text-sm text-slate-500">
                Melde dich mit deiner beim Verein hinterlegten E-Mail-Adresse an.
            </p>

        </div>


        {{-- Login --}}
        <div class="px-8 py-7">

            <div>

                <label
                    for="member-email"
                    class="mb-1.5 block text-sm font-medium text-slate-700"
                >
                    E-Mail-Adresse
                </label>

                <input
                    id="member-email"
                    type="email"
                    wire:model="email"
                    wire:keydown.enter="requestLoginLink"
                    autocomplete="email"
                    autofocus
                    class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-slate-900 outline-none transition focus:border-slate-500 focus:ring-2 focus:ring-slate-200"
                >

                @error('email')
                <p class="mt-1 text-xs text-red-600">
                    {{ $message }}
                </p>
                @enderror

            </div>


            <button
                type="button"
                wire:click="requestLoginLink"
                wire:loading.attr="disabled"
                wire:target="requestLoginLink"
                class="mt-5 flex w-full items-center justify-center gap-2 rounded-lg bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60"
            >

                <svg
                    wire:loading
                    wire:target="requestLoginLink"
                    class="h-4 w-4 animate-spin"
                    xmlns="http://www.w3.org/2000/svg"
                    fill="none"
                    viewBox="0 0 24 24"
                >
                    <circle
                        class="opacity-25"
                        cx="12"
                        cy="12"
                        r="10"
                        stroke="currentColor"
                        stroke-width="4"
                    ></circle>

                    <path
                        class="opacity-75"
                        fill="currentColor"
                        d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"
                    ></path>
                </svg>

                <span
                    wire:loading.remove
                    wire:target="requestLoginLink"
                >
                    Login-Link anfordern
                </span>

                <span
                    wire:loading
                    wire:target="requestLoginLink"
                >
                    Link wird versendet …
                </span>

            </button>


            @if($message)

                <div class="mt-5 rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700">
                    {{ $message }}
                </div>

            @endif

        </div>


        {{-- Interner Zugang --}}
        <div class="border-t border-slate-200 bg-slate-50 px-8 py-6 text-center">

            <div class="text-sm font-medium text-slate-700">
                Vereinsverwaltung?
            </div>

            <p class="mt-1 text-xs text-slate-500">
                Interne Benutzer melden sich mit Benutzername und Passwort an.
            </p>

            <a
                href="{{ url('/login') }}"
                class="mt-4 inline-flex w-full items-center justify-center rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-100"
            >
                Zum internen Login
            </a>

        </div>

    </div>


    <div class="mt-5 text-center text-xs text-slate-400">
        VEMA · Vereinsmanagement
    </div>

</div>
