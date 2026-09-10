<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>VEMA Login</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="min-h-screen bg-gray-100 flex items-center justify-center">

<div class="w-full max-w-md bg-white rounded-xl shadow p-8">

    <h1 class="text-2xl font-semibold mb-6">
        VEMA
    </h1>

    <form method="POST" action="/login">
        @csrf

        <div class="mb-4">

            <label class="block mb-1">
                Benutzername
            </label>

            <input
                type="text"
                name="username"
                value="{{ old('username') }}"
                class="w-full border rounded px-3 py-2"
                autofocus
            >

        </div>

        <div class="mb-4">

            <label class="block mb-1">
                Passwort
            </label>

            <input
                type="password"
                name="password"
                class="w-full border rounded px-3 py-2"
            >

        </div>

        @error('username')
        <div class="text-red-600 mb-4">
            {{ $message }}
        </div>
        @enderror

        <button
            type="submit"
            class="w-full bg-blue-700 text-white rounded px-4 py-2"
        >
            Anmelden
        </button>

    </form>

</div>

</body>
</html>
