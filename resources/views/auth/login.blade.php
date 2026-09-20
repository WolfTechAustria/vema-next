<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>VEMA Login</title>

    @vite([
        'resources/css/app.css',
        'resources/js/app.js'
    ])
</head>

<body class="flex min-h-screen items-center justify-center bg-slate-100 px-4">

<div class="w-full max-w-md">

    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xl">

        {{-- Kopf --}}
        <div class="border-b border-slate-200 px-8 py-8 text-center">

            <img
                src="{{ asset('images/logo.jpg') }}"
                alt="VEMA"
                class="mx-auto mb-5 max-h-50 w-auto"
            >


        </div>


        {{-- Login --}}
        <div class="px-8 py-7">

            @if(session('success'))
                <div class="mb-5 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
                    {{ session('success') }}
                </div>
            @endif

            <form
                id="login-form"
                method="POST"
                action="/login"
                class="space-y-5"
            >
                @csrf


                <div>

                    <label
                        for="username"
                        class="mb-1.5 block text-sm font-medium text-slate-700"
                    >
                        Benutzername
                    </label>

                    <input
                        id="username"
                        type="text"
                        name="username"
                        value="{{ old('username') }}"
                        autocomplete="username"
                        autofocus
                        class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-slate-900 outline-none transition focus:border-slate-500 focus:ring-2 focus:ring-slate-200"
                    >

                </div>


                <div>

                    <div class="mb-1.5 flex items-center justify-between">
                        <label
                            for="password"
                            class="block text-sm font-medium text-slate-700"
                        >
                            Passwort
                        </label>

                        <a
                            href="{{ route('password.request') }}"
                            class="text-xs font-medium text-slate-500 hover:text-slate-800"
                        >
                            Passwort vergessen?
                        </a>
                    </div>

                    <input
                        id="password"
                        type="password"
                        name="password"
                        autocomplete="current-password"
                        class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-slate-900 outline-none transition focus:border-slate-500 focus:ring-2 focus:ring-slate-200"
                    >

                </div>


                @error('username')

                <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    {{ $message }}
                </div>

                @enderror


                <button
                    id="login-button"
                    type="submit"
                    class="flex w-full items-center justify-center gap-2 rounded-lg bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60"
                >
                    <svg
                        id="login-spinner"
                        class="hidden h-4 w-4 animate-spin"
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

                    <span id="login-button-text">
                        Anmelden
                    </span>
                </button>

            </form>

        </div>


        {{-- Mitgliederzugang --}}
        <div class="border-t border-slate-200 bg-slate-50 px-8 py-6">

            <div class="text-center">

                <div class="text-sm font-medium text-slate-700">
                    Mitglied der Schützengilde?
                </div>

                <p class="mt-1 text-xs text-slate-500">
                    Melde dich einfach mit deiner hinterlegten E-Mail-Adresse an.
                </p>


                <a
                    href="{{ route('member.login') }}"
                    class="mt-4 inline-flex w-full items-center justify-center rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-100"
                >
                    Zum Mitglieder-Login
                </a>

            </div>

        </div>

    </div>


    <div class="mt-5 text-center text-xs text-slate-400">
        VEMA · Vereinsmanagement
    </div>

</div>


<script>
    function resetLoginButton() {
        const button = document.getElementById('login-button');
        const spinner = document.getElementById('login-spinner');
        const text = document.getElementById('login-button-text');

        if (!button || !spinner || !text) {
            return;
        }

        button.disabled = false;

        spinner.classList.add('hidden');

        text.textContent = 'Anmelden';
    }

    function initLoginForm() {
        const form = document.getElementById('login-form');
        const button = document.getElementById('login-button');
        const spinner = document.getElementById('login-spinner');
        const text = document.getElementById('login-button-text');

        if (!form || !button || !spinner || !text) {
            return;
        }

        resetLoginButton();

        form.addEventListener('submit', () => {

            button.disabled = true;

            spinner.classList.remove('hidden');

            text.textContent = 'Anmeldung läuft …';
        });
    }

    document.addEventListener('DOMContentLoaded', () => {
        initLoginForm();
    });

    window.addEventListener('pageshow', () => {
        resetLoginButton();
    });
</script>
</body>

</html>
