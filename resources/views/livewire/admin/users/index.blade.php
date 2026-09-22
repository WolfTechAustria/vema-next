<div class="space-y-6">

    @include('partials.flash-messages')

    @include('partials.admin-settings-nav')

    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">

        <div>
            <h2 class="text-2xl font-bold tracking-tight">
                Benutzerverwaltung
            </h2>

            <p class="mt-1 text-sm text-slate-500">
                Mitgliedern Zugang zu VEMA geben, Admin-Rechte vergeben, Benutzer sperren.
            </p>
        </div>

        @unless($showInviteForm)
            <button
                type="button"
                wire:click="$set('showInviteForm', true)"
                class="rounded-lg bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white"
            >
                + Mitglied einladen
            </button>
        @endunless

    </div>

    @if($showInviteForm)

        <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">

            <h3 class="mb-4 font-semibold text-slate-900">
                Mitglied zu VEMA einladen
            </h3>

            @if(!$selectedMemberId)

                <label class="mb-1 block text-sm font-medium text-slate-700">
                    Mitglied suchen
                </label>

                <input
                    type="text"
                    wire:model.live.debounce.300ms="memberSearch"
                    placeholder="Name eingeben ..."
                    class="w-full max-w-md rounded-lg border border-slate-300 px-3 py-2"
                >

                <div class="mt-3 max-w-md divide-y divide-slate-100 rounded-lg border border-slate-200">

                    @forelse($availableMembers as $member)
                        <button
                            type="button"
                            wire:click="selectMember({{ $member->memberID }})"
                            class="flex w-full items-center justify-between px-4 py-2 text-left text-sm hover:bg-slate-50"
                        >
                            <span>{{ $member->surname }} {{ $member->name }}</span>
                            <span class="text-xs text-slate-400">#{{ $member->memberID }}</span>
                        </button>
                    @empty
                        @if($memberSearch !== '')
                            <div class="px-4 py-3 text-sm text-slate-500">
                                Keine passenden Mitglieder ohne bestehenden Zugang gefunden.
                            </div>
                        @endif
                    @endforelse

                </div>

                <div class="mt-4">
                    <button type="button" wire:click="cancelInvite" class="text-sm font-medium text-slate-500 hover:text-slate-800">
                        Abbrechen
                    </button>
                </div>

            @else

                <div class="grid max-w-md gap-4">

                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">
                            Benutzername
                        </label>

                        <input type="text" wire:model="newUsername" class="w-full rounded-lg border border-slate-300 px-3 py-2">

                        @error('newUsername')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">
                            E-Mail-Adresse
                        </label>

                        <input type="email" wire:model="newEmail" class="w-full rounded-lg border border-slate-300 px-3 py-2">

                        @error('newEmail')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror

                        <p class="mt-1 text-xs text-slate-500">
                            An diese Adresse wird der Link zum Festlegen des Passworts verschickt.
                        </p>
                    </div>

                    <label class="flex items-center gap-3">
                        <input type="checkbox" wire:model="grantAdmin" class="rounded border-slate-300">
                        <span class="text-sm font-medium text-slate-700">Admin-Rechte geben</span>
                    </label>

                </div>

                <div class="mt-5 flex gap-2">
                    <button type="button" wire:click="cancelInvite" class="rounded-lg border border-slate-300 px-4 py-2 text-sm">
                        Abbrechen
                    </button>

                    <button type="button" wire:click="createUser" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white">
                        Benutzer anlegen &amp; einladen
                    </button>
                </div>

            @endif

        </section>

    @endif

    <section class="rounded-xl border border-slate-200 bg-white shadow-sm">

        <div class="overflow-x-auto">

            <table class="min-w-full divide-y divide-slate-200">

                <thead class="bg-slate-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Benutzer</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Mitglied</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">E-Mail</th>
                    <th class="px-6 py-3 text-center text-xs font-semibold uppercase tracking-wide text-slate-500">Status</th>
                    <th class="px-6 py-3 text-center text-xs font-semibold uppercase tracking-wide text-slate-500">Admin</th>
                    <th class="px-6 py-3 text-center text-xs font-semibold uppercase tracking-wide text-slate-500">Kassier</th>
                    <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500"></th>
                </tr>
                </thead>

                <tbody class="divide-y divide-slate-100">

                @foreach($users as $user)
                    <tr class="hover:bg-slate-50" wire:key="user-{{ $user->id }}">

                        <td class="px-6 py-4">
                            <div class="font-medium text-slate-900">
                                {{ $user->username }}
                                @if($user->id === auth()->id())
                                    <span class="ml-1 text-xs text-slate-400">(du)</span>
                                @endif
                            </div>
                        </td>

                        <td class="px-6 py-4 text-sm text-slate-600">
                            {{ $user->member?->full_name ?? '–' }}
                        </td>

                        <td class="px-6 py-4 text-sm text-slate-600">
                            {{ $user->email }}
                        </td>

                        <td class="px-6 py-4 text-center">
                            <button
                                type="button"
                                wire:click="toggleEnabled({{ $user->id }})"
                                class="inline-flex rounded-full px-3 py-1 text-xs font-semibold
                                {{ $user->enabled ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700' }}"
                            >
                                {{ $user->enabled ? 'Aktiv' : 'Gesperrt' }}
                            </button>
                        </td>

                        <td class="px-6 py-4 text-center">
                            <button
                                type="button"
                                wire:click="toggleAdmin({{ $user->id }})"
                                class="inline-flex rounded-full px-3 py-1 text-xs font-semibold
                                {{ $user->hasRole('admin') ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-500' }}"
                            >
                                {{ $user->hasRole('admin') ? 'Admin' : 'Mitarbeiter' }}
                            </button>
                        </td>

                        <td class="px-6 py-4 text-center">
                            <button
                                type="button"
                                wire:click="toggleKassier({{ $user->id }})"
                                class="inline-flex rounded-full px-3 py-1 text-xs font-semibold
                                {{ $user->hasRole('kassier') ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-500' }}"
                            >
                                {{ $user->hasRole('kassier') ? 'Kassier' : '–' }}
                            </button>
                        </td>

                        <td class="px-6 py-4 text-right">
                            <button
                                type="button"
                                wire:click="resendInvite({{ $user->id }})"
                                wire:confirm="Link zum Passwort-Zurücksetzen erneut an {{ $user->email }} verschicken?"
                                class="text-sm font-medium text-slate-500 hover:text-slate-800"
                            >
                                Link erneut senden
                            </button>
                        </td>

                    </tr>
                @endforeach

                </tbody>

            </table>

        </div>

    </section>

</div>
