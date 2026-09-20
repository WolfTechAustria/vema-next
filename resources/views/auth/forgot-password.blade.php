<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Passwort vergessen | VEMA</title>

    @vite([
        'resources/css/app.css',
        'resources/js/app.js'
    ])
</head>

<body class="flex min-h-screen items-center justify-center bg-slate-100 px-4">

<div class="w-full max-w-md">

    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xl">

        <div class="border-b border-slate-200 px-8 py-8 text-center">

            <img
                src="{{ asset('images/logo.jpg') }}"
                alt="VEMA"
                class="mx-auto mb-5 max-h-50 w-auto"
            >

            <h1 class="text-lg font-semibold text-slate-900">
                Passwort vergessen
            </h1>

            <p class="mt-1 text-sm text-slate-500">
                Gib deine E-Mail-Adresse ein, wir schicken dir einen Link zum Zurücksetzen.
            </p>

        </div>

        <div class="px-8 py-7">

            @if(session('success'))
                <div class="mb-5 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
                    {{ session('success') }}
                </div>
            @endif

            <form method="POST" action="{{ route('password.email') }}" class="space-y-5">
                @csrf

                <div>
                    <label for="email" class="mb-1.5 block text-sm font-medium text-slate-700">
                        E-Mail-Adresse
                    </label>

                    <input
                        id="email"
                        type="email"
                        name="email"
                        value="{{ old('email') }}"
                        autocomplete="email"
                        autofocus
                        class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-slate-900 outline-none transition focus:border-slate-500 focus:ring-2 focus:ring-slate-200"
                    >
                </div>

                @error('email')
                    <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                        {{ $message }}
                    </div>
                @enderror

                <button
                    type="submit"
                    class="flex w-full items-center justify-center gap-2 rounded-lg bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-800"
                >
                    Link anfordern
                </button>

            </form>

            <div class="mt-5 text-center">
                <a href="{{ route('login') }}" class="text-sm font-medium text-slate-600 hover:text-slate-900">
                    Zurück zum Login
                </a>
            </div>

        </div>

    </div>

    <div class="mt-5 text-center text-xs text-slate-400">
        VEMA · Vereinsmanagement
    </div>

</div>

</body>

</html>
