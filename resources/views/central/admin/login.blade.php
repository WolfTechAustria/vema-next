@component('layouts.central', ['title' => 'Plattform-Admin | VEMA'])
    <div class="mx-auto w-full max-w-sm overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xl">

        <div class="border-b border-slate-200 px-6 py-6 text-center">
            <h1 class="text-xl font-bold tracking-tight text-slate-900">Plattform-Admin</h1>
            <p class="mt-1 text-sm text-slate-500">Verwaltung der Vereine auf {{ config('tenancy.tenant_domain') }}</p>
        </div>

        <form method="POST" action="{{ route('central.admin.login.store') }}" class="space-y-4 px-6 py-6">
            @csrf

            <div>
                <label for="email" class="mb-1.5 block text-sm font-medium text-slate-700">E-Mail-Adresse</label>
                <input
                    id="email"
                    type="email"
                    name="email"
                    value="{{ old('email') }}"
                    autocomplete="username"
                    required
                    autofocus
                    class="w-full rounded-lg border px-3 py-2.5 text-base {{ $errors->has('email') ? 'border-red-400' : 'border-slate-300' }}"
                >
                @error('email')
                    <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="password" class="mb-1.5 block text-sm font-medium text-slate-700">Passwort</label>
                <input
                    id="password"
                    type="password"
                    name="password"
                    autocomplete="current-password"
                    required
                    class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-base"
                >
                @error('password')
                    <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <button type="submit" class="flex w-full justify-center rounded-lg bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-800">
                Anmelden
            </button>
        </form>

    </div>
@endcomponent
