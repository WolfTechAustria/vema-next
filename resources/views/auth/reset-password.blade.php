<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Passwort zurücksetzen | VEMA</title>

    @vite([
        'resources/css/app.css',
        'resources/js/app.js'
    ])
</head>

<body class="flex min-h-screen items-center justify-center bg-slate-100 px-4">

<div class="w-full max-w-md">

    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xl">

        <div class="border-b border-slate-200 px-8 py-8 text-center">

            @php
                $clubLogoUrl = app(\App\Services\ClubBranding::class)->logoUrl();
            @endphp

            @if($clubLogoUrl)
                <img
                    src="{{ $clubLogoUrl }}"
                    alt="VEMA"
                    class="mx-auto mb-5 max-h-50 w-auto"
                >
            @else
                <p class="mb-5 text-2xl font-bold tracking-tight text-slate-900">
                    {{ \App\Models\Setting::current()->name }}
                </p>
            @endif

            <h1 class="text-lg font-semibold text-slate-900">
                Neues Passwort festlegen
            </h1>

        </div>

        <div class="px-8 py-7">

            @error('email')
                <div class="mb-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    {{ $message }}
                </div>
            @enderror

            <form method="POST" action="{{ route('password.update') }}" class="space-y-5">
                @csrf

                <input type="hidden" name="token" value="{{ $token }}">

                <div>
                    <label for="email" class="mb-1.5 block text-sm font-medium text-slate-700">
                        E-Mail-Adresse
                    </label>

                    <input
                        id="email"
                        type="email"
                        name="email"
                        value="{{ old('email', $email) }}"
                        autocomplete="email"
                        autofocus
                        class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-slate-900 outline-none transition focus:border-slate-500 focus:ring-2 focus:ring-slate-200"
                    >
                </div>

                <div>
                    <label for="password" class="mb-1.5 block text-sm font-medium text-slate-700">
                        Neues Passwort
                    </label>

                    <input
                        id="password"
                        type="password"
                        name="password"
                        autocomplete="new-password"
                        class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-slate-900 outline-none transition focus:border-slate-500 focus:ring-2 focus:ring-slate-200"
                    >

                    @error('password')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="password_confirmation" class="mb-1.5 block text-sm font-medium text-slate-700">
                        Neues Passwort bestätigen
                    </label>

                    <input
                        id="password_confirmation"
                        type="password"
                        name="password_confirmation"
                        autocomplete="new-password"
                        class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-slate-900 outline-none transition focus:border-slate-500 focus:ring-2 focus:ring-slate-200"
                    >
                </div>

                <button
                    type="submit"
                    class="flex w-full items-center justify-center gap-2 rounded-lg bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-800"
                >
                    Passwort speichern
                </button>

            </form>

        </div>

    </div>

    <div class="mt-5 text-center text-xs text-slate-400">
        VEMA · Vereinsmanagement
    </div>

</div>

</body>

</html>
