<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>VEMA – Vereinsverwaltung</title>

    @vite([
        'resources/css/app.css',
        'resources/js/app.js'
    ])
</head>

<body class="flex min-h-screen items-center justify-center bg-slate-100 px-4">

<div class="w-full max-w-lg overflow-hidden rounded-2xl border border-slate-200 bg-white px-8 py-10 text-center shadow-xl">

    <h1 class="text-3xl font-bold tracking-tight text-slate-900">
        VEMA
    </h1>

    <p class="mt-2 text-base text-slate-600">
        Mitglieder, Beiträge, Dienstplan, Rundschreiben und Kassabuch für deinen Verein.
    </p>

    <p class="mt-6 text-sm text-slate-500">
        Bereits registriert? Dein Verein ist unter
        <span class="font-medium text-slate-700">&lt;verein&gt;.{{ config('tenancy.central_domain') }}</span>
        erreichbar.
    </p>

</div>

</body>

</html>
