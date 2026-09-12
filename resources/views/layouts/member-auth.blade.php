<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        {{ $title ?? 'Mitgliederbereich | VEMA' }}
    </title>

    @vite([
        'resources/css/app.css',
        'resources/js/app.js'
    ])
</head>

<body class="min-h-screen bg-slate-100 text-slate-900">

<div class="flex min-h-screen items-center justify-center px-4 py-10">

    <div class="w-full">
        {{ $slot }}
    </div>

</div>

</body>

</html>
