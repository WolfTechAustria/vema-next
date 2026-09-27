<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>{{ $tenant->name }} | VEMA</title>

    @vite([
        'resources/css/app.css',
        'resources/js/app.js'
    ])
</head>

<body class="flex min-h-screen items-center justify-center bg-slate-100 px-4">

<div class="w-full max-w-md overflow-hidden rounded-2xl border border-slate-200 bg-white px-8 py-10 text-center shadow-xl">

    <h1 class="text-2xl font-bold tracking-tight text-slate-900">
        {{ $tenant->name }}
    </h1>

    <p class="mt-4 text-sm text-slate-600">
        @if($tenant->status === \App\Enums\TenantStatus::Pending)
            Dieser Verein ist registriert, der Zugang wird aber erst nach der Freischaltung aktiviert.
        @elseif($tenant->status === \App\Enums\TenantStatus::Suspended)
            Der Zugang dieses Vereins ist derzeit gesperrt.
        @else
            Die Lizenz dieses Vereins ist abgelaufen.
        @endif
    </p>

    <p class="mt-2 text-sm text-slate-500">
        Bei Fragen wende dich bitte an den Vorstand deines Vereins.
    </p>

</div>

</body>

</html>
