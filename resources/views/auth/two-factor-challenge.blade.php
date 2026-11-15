<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Anmeldung bestätigen | VEMA</title>

    @include('partials.favicon')
    @include('partials.pwa-head', ['forMembers' => false])

    @vite([
        'resources/css/app.css',
        'resources/js/app.js'
    ])
</head>

<body class="flex min-h-screen items-center justify-center bg-slate-100 px-4">

<div class="w-full max-w-md">

    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xl">

        <div class="border-b border-slate-200 px-8 py-6">
            <h1 class="text-lg font-semibold text-slate-900">Anmeldung bestätigen</h1>

            <p class="mt-1 text-sm text-slate-500">
                @if($usesEmail)
                    Wir haben einen sechsstelligen Code an deine Login-E-Mail geschickt.
                @else
                    Gib den sechsstelligen Code aus deiner Authenticator-App ein.
                @endif
            </p>
        </div>

        <div class="px-8 py-7">

            @if(session('success'))
                <div class="mb-5 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
                    {{ session('success') }}
                </div>
            @endif

            <form method="POST" action="{{ route('two-factor.challenge') }}" class="space-y-5">
                @csrf

                <div>
                    <label for="code" class="mb-1.5 block text-sm font-medium text-slate-700">Code</label>

                    <input
                        id="code"
                        type="text"
                        name="code"
                        inputmode="numeric"
                        autocomplete="one-time-code"
                        autocapitalize="none"
                        spellcheck="false"
                        required
                        autofocus
                        class="w-full rounded-lg border bg-white px-3 py-2.5 text-center text-xl tracking-widest text-slate-900 outline-none transition focus:ring-2
                        {{ $errors->has('code') ? 'border-red-400 focus:border-red-500 focus:ring-red-100' : 'border-slate-300 focus:border-slate-500 focus:ring-slate-200' }}"
                    >

                    <p class="mt-1.5 text-xs text-slate-500">
                        Kein Zugriff? Gib stattdessen einen deiner Wiederherstellungscodes ein.
                    </p>
                </div>

                <label class="flex items-center gap-2 text-sm text-slate-700">
                    <input type="checkbox" name="trust_device" value="1" class="rounded border-slate-300">
                    Diesem Gerät {{ $trustDays }} Tage vertrauen
                </label>

                @if($errors->any())
                    <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700" role="alert">
                        @foreach($errors->all() as $error)
                            <div>{{ $error }}</div>
                        @endforeach
                    </div>
                @endif

                <button type="submit" class="w-full rounded-lg bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-800">
                    Bestätigen
                </button>
            </form>

            <div class="mt-5 flex items-center justify-between text-xs">
                <a href="{{ route('login') }}" class="font-medium text-slate-500 hover:text-slate-800">← Zurück zur Anmeldung</a>

                @if($usesEmail)
                    <form method="POST" action="{{ route('two-factor.resend') }}">
                        @csrf
                        <button type="submit" class="font-medium text-slate-500 hover:text-slate-800">Neuen Code senden</button>
                    </form>
                @endif
            </div>

        </div>
    </div>

</div>

</body>
</html>
