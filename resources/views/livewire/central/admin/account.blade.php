<div class="space-y-5">

    <h1 class="text-2xl font-bold tracking-tight">Mein Konto</h1>

    @if(session('success'))
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</div>
    @endif

    <div class="grid gap-5 lg:grid-cols-2">

        <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="mb-4 font-semibold">Profil</h2>

            <form wire:submit="saveProfile" class="space-y-4">
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Name</label>
                    <input type="text" wire:model="name" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                    @error('name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">E-Mail (Anmeldung)</label>
                    <input type="email" wire:model="email" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                    @error('email') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <button type="submit" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">
                    Speichern
                </button>
            </form>
        </section>

        <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="mb-4 font-semibold">Passwort ändern</h2>

            <form wire:submit="changePassword" class="space-y-4">
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Aktuelles Passwort</label>
                    <input type="password" wire:model="current_password" autocomplete="current-password" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                    @error('current_password') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Neues Passwort (mind. 12 Zeichen)</label>
                    <input type="password" wire:model="password" autocomplete="new-password" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                    @error('password') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Neues Passwort wiederholen</label>
                    <input type="password" wire:model="password_confirmation" autocomplete="new-password" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                </div>

                <button type="submit" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">
                    Passwort ändern
                </button>
            </form>
        </section>

    </div>

    <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <h2 class="mb-1 font-semibold">Plattform-Admins</h2>
        <p class="mb-4 text-sm text-slate-500">
            Weitere Admins legst du auf dem Server an: <code class="rounded bg-slate-100 px-1">php artisan platform:admin-create &lt;email&gt;</code>
        </p>

        <ul class="divide-y divide-slate-100 text-sm">
            @foreach($admins as $platformAdmin)
                <li wire:key="admin-{{ $platformAdmin->id }}" class="flex flex-wrap justify-between gap-2 py-2">
                    <span>{{ $platformAdmin->name }} · {{ $platformAdmin->email }}</span>
                    <span class="text-slate-500">zuletzt angemeldet: {{ $platformAdmin->last_login_at?->format('d.m.Y H:i') ?? '–' }}</span>
                </li>
            @endforeach
        </ul>
    </section>

</div>
