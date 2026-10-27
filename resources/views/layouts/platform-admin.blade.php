<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        {{ $title ?? 'Plattform-Admin | VEMA' }}
    </title>

    @vite([
        'resources/css/app.css',
        'resources/js/app.js'
    ])
</head>

<body class="min-h-screen bg-slate-100 text-slate-900">

<header class="border-b border-slate-200 bg-white">
    <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-3 px-4 py-3">
        <a href="{{ route('central.admin.tenants') }}" wire:navigate class="font-bold tracking-tight">
            VEMA · Plattform-Admin
        </a>

        <div class="flex items-center gap-3 text-sm">
            <a href="{{ route('central.admin.account') }}" wire:navigate class="text-slate-500 hover:text-slate-800 hover:underline">{{ auth('platform')->user()?->name }}</a>

            <form method="POST" action="{{ route('central.admin.logout') }}">
                @csrf
                <button type="submit" class="rounded-lg border border-slate-300 px-3 py-1.5 font-medium text-slate-700 hover:bg-slate-50">
                    Abmelden
                </button>
            </form>
        </div>
    </div>
</header>

<main class="mx-auto max-w-6xl px-4 py-6">
    {{ $slot }}
</main>

</body>

</html>
