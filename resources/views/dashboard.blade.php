<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">

    <title>VEMA Dashboard</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="bg-gray-100 min-h-screen">

<div class="max-w-6xl mx-auto p-8">

    <div class="flex justify-between items-center mb-8">

        <div>
            <h1 class="text-3xl font-semibold">
                VEMA
            </h1>

            <p class="text-gray-600">
                Willkommen {{ auth()->user()->username }}
            </p>
        </div>

        <form method="POST" action="{{ route('logout') }}">
            @csrf

            <button class="border rounded px-4 py-2">
                Abmelden
            </button>
        </form>

    </div>

    <div class="bg-white shadow rounded-xl p-6">

        <div class="text-gray-500">
            Aktive Mitglieder
        </div>

        <div class="text-4xl font-bold">
            {{ $activeMembers }}
        </div>

    </div>

</div>

</body>
</html>
