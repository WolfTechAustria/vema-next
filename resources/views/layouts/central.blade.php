<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        {{ $title ?? 'VEMA' }}
    </title>

    @vite([
        'resources/css/app.css',
        'resources/js/app.js'
    ])
</head>

<body class="min-h-screen bg-slate-100 text-slate-900">

<div class="flex min-h-screen flex-col items-center justify-center px-4 py-10">

    <div class="w-full">
        {{ $slot }}
    </div>

    <p class="mt-8 text-center text-xs text-slate-500">
        <a href="{{ config('tenancy.website_url') }}" class="hover:text-slate-800">VEMA · Vereinsverwaltung</a>
        ·
        <a href="{{ config('tenancy.website_url') }}/impressum.html" class="hover:text-slate-800">Impressum</a>
        ·
        <a href="{{ config('tenancy.website_url') }}/datenschutz.html" class="hover:text-slate-800">Datenschutz</a>
    </p>

</div>

</body>

</html>
