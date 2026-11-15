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
                class="w-full rounded-lg bg-slate-900 px-4 py-2.5 sm:w-auto text-sm font-semibold text-white"
            >
                + Mitglied einladen
            </button>
        @endunless

    </div>

    <section class="flex flex-col gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:flex-row sm:items-center sm:justify-between sm:p-6">
        <div>
            <h3 class="font-semibold text-slate-900">Zwei-Faktor-Anmeldung</h3>
            <p class="mt-1 text-sm text-slate-500">
                @if($twoFactorRequired)
                    Pflicht für alle Funktionäre: Nach dem Passwort ist ein Code aus der Authenticator-App oder per E-Mail nötig.
                    Wer sie noch nicht eingerichtet hat, wird bei der nächsten Anmeldung dazu aufgefordert.
                @else
                    Ausgeschaltet. Eingeschaltet müssen alle Funktionäre beim Anmelden zusätzlich einen Code eingeben.
                @endif
            </p>
        </div>

        <button
            type="button"
            wire:click="toggleTwoFactorRequired"
            wire:confirm="{{ $twoFactorRequired ? 'Zwei-Faktor-Anmeldung für alle Funktionäre ausschalten?' : 'Zwei-Faktor-Anmeldung für alle Funktionäre verpflichtend machen? Auch du musst sie danach sofort einrichten.' }}"
            aria-pressed="{{ $twoFactorRequired ? 'true' : 'false' }}"
            class="shrink-0 rounded-lg px-4 py-2 text-sm font-semibold {{ $twoFactorRequired ? 'border border-slate-300 text-slate-700 hover:bg-slate-50' : 'bg-slate-900 text-white hover:bg-slate-800' }}"
        >
            {{ $twoFactorRequired ? 'Ausschalten' : 'Verpflichtend machen' }}
        </button>
    </section>

    @if($showInviteForm)

        <section class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:p-6">

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
                    class="w-full max-w-md rounded-lg border border-slate-300 px-3 py-2.5 text-base sm:py-2 sm:text-sm"
                >

                <div class="mt-3 max-w-md divide-y divide-slate-100 rounded-lg border border-slate-200">

                    @forelse($availableMembers as $member)
                        <button
                            type="button"
                            wire:click="selectMember({{ $member->memberID }})"
                            class="flex w-full items-center justify-between px-4 py-3 text-left sm:py-2 text-sm hover:bg-slate-50"
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

                <div class="mt-5 flex flex-col-reverse gap-2 sm:flex-row">
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

            <table class="block min-w-full md:table md:divide-y md:divide-slate-200">

                <thead class="hidden bg-slate-50 md:table-header-group">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Benutzer</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Mitglied</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">E-Mail</th>
                    <th class="px-6 py-3 text-center text-xs font-semibold uppercase tracking-wide text-slate-500">Status</th>
                    <th class="px-6 py-3 text-center text-xs font-semibold uppercase tracking-wide text-slate-500">Admin</th>
                    <th class="px-6 py-3 text-center text-xs font-semibold uppercase tracking-wide text-slate-500">Kassier</th>
                    <th class="px-6 py-3 text-center text-xs font-semibold uppercase tracking-wide text-slate-500">Homepage</th>
                    @if($inventoryEnabled)
                        <th class="px-6 py-3 text-center text-xs font-semibold uppercase tracking-wide text-slate-500">Zeugwart</th>
                    @endif
                    <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500"></th>
                </tr>
                </thead>

                <tbody class="block divide-y divide-slate-100 md:table-row-group">

                @foreach($users as $user)
                    <tr
                        class="grid grid-cols-2 gap-x-2 gap-y-2 p-4 sm:grid-cols-4 hover:bg-slate-50 md:table-row md:p-0"
                        wire:key="user-{{ $user->id }}"
                    >

                        <td class="col-span-2 sm:col-span-4 md:table-cell md:px-6 md:py-4">
                            <div class="font-medium text-slate-900">
                                {{ $user->username }}
                                @if($user->id === auth()->id())
                                    <span class="ml-1 text-xs text-slate-400">(du)</span>
                                @endif
                            </div>

                            @if($user->two_factor_confirmed_at)
                                <div class="mt-0.5 flex items-center gap-2 text-xs text-emerald-700">
                                    2FA: {{ \App\Services\TwoFactorAuthentication::methodLabels()[$user->two_factor_method] ?? '–' }}
                                    <button
                                        type="button"
                                        wire:click="resetTwoFactor({{ $user->id }})"
                                        wire:confirm="Zwei-Faktor-Anmeldung von „{{ $user->username }}“ zurücksetzen? Der Zugang muss sie bei der nächsten Anmeldung neu einrichten."
                                        class="font-medium text-slate-500 underline hover:text-slate-800"
                                    >
                                        zurücksetzen
                                    </button>
                                </div>
                            @elseif($twoFactorRequired)
                                <div class="mt-0.5 text-xs text-amber-700">2FA noch nicht eingerichtet</div>
                            @endif

                            <div class="text-sm text-slate-500 md:hidden">
                                {{ $user->member?->full_name ?? '–' }}
                            </div>
                        </td>

                        <td class="hidden px-6 py-4 text-sm text-slate-600 md:table-cell">
                            {{ $user->member?->full_name ?? '–' }}
                        </td>

                        <td class="col-span-2 sm:col-span-4 -mt-2 break-all text-sm text-slate-500 md:mt-0 md:table-cell md:px-6 md:py-4 md:text-slate-600">
                            @if($editingEmailUserId === $user->id)
                                <form wire:submit="saveEmail" class="space-y-2">
                                    <input
                                        type="email"
                                        wire:model="editedEmail"
                                        aria-label="Login-E-Mail"
                                        class="w-full min-w-56 rounded-lg border border-slate-300 px-3 py-1.5 text-sm"
                                    >

                                    @error('editedEmail')
                                        <p class="text-xs text-red-600">{{ $message }}</p>
                                    @enderror

                                    <div class="flex gap-2">
                                        <button type="submit" class="rounded-lg bg-slate-900 px-3 py-1 text-xs font-semibold text-white">Speichern</button>
                                        <button type="button" wire:click="cancelEmail" class="rounded-lg border border-slate-300 px-3 py-1 text-xs">Abbrechen</button>
                                    </div>
                                </form>
                            @else
                                @php
                                    $memberEmails = $user->member?->emails->map(fn ($memberEmail) => mb_strtolower(trim((string) $memberEmail->email))) ?? collect();
                                    $isOutsideMemberData = $user->member && $user->email && ! $memberEmails->contains(mb_strtolower(trim($user->email)));
                                @endphp

                                {{ $user->email }}

                                <button
                                    type="button"
                                    wire:click="editEmail({{ $user->id }})"
                                    class="ml-1 text-xs text-slate-400 underline hover:text-slate-700"
                                    title="Login-E-Mail ändern"
                                >ändern</button>

                                @if($isOutsideMemberData)
                                    <span class="mt-1 block text-xs text-amber-700">nicht in den Mitgliedsdaten</span>
                                @endif
                            @endif
                        </td>

                        <td class="md:table-cell md:px-6 md:py-4 md:text-center">
                            <div class="mb-1 text-xs text-slate-400 md:hidden">Zugang</div>
                            <button
                                type="button"
                                wire:click="toggleEnabled({{ $user->id }})"
                                class="inline-flex w-full justify-center rounded-full px-3 py-2 text-xs font-semibold md:w-auto md:py-1
                                {{ $user->enabled ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700' }}"
                            >
                                {{ $user->enabled ? 'Aktiv' : 'Gesperrt' }}
                            </button>
                        </td>

                        <td class="md:table-cell md:px-6 md:py-4 md:text-center">
                            <div class="mb-1 text-xs text-slate-400 md:hidden">Rolle</div>
                            <button
                                type="button"
                                wire:click="toggleAdmin({{ $user->id }})"
                                class="inline-flex w-full justify-center rounded-full px-3 py-2 text-xs font-semibold md:w-auto md:py-1
                                {{ $user->hasRole('admin') ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-500' }}"
                            >
                                {{ $user->hasRole('admin') ? 'Admin' : 'Mitarbeiter' }}
                            </button>
                        </td>

                        <td class="md:table-cell md:px-6 md:py-4 md:text-center">
                            <div class="mb-1 text-xs text-slate-400 md:hidden">Kassier</div>
                            <button
                                type="button"
                                wire:click="toggleKassier({{ $user->id }})"
                                class="inline-flex w-full justify-center rounded-full px-3 py-2 text-xs font-semibold md:w-auto md:py-1
                                {{ $user->hasRole('kassier') ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-500' }}"
                            >
                                {{ $user->hasRole('kassier') ? 'Kassier' : '–' }}
                            </button>
                        </td>

                        <td class="md:table-cell md:px-6 md:py-4 md:text-center">
                            <div class="mb-1 text-xs text-slate-400 md:hidden">Homepage</div>
                            <button
                                type="button"
                                wire:click="toggleHomepage({{ $user->id }})"
                                class="inline-flex w-full justify-center rounded-full px-3 py-2 text-xs font-semibold md:w-auto md:py-1
                                {{ $user->hasRole('homepage') ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-500' }}"
                            >
                                {{ $user->hasRole('homepage') ? 'Homepage' : '–' }}
                            </button>
                        </td>

                        @if($inventoryEnabled)
                            <td class="md:table-cell md:px-6 md:py-4 md:text-center">
                                <div class="mb-1 text-xs text-slate-400 md:hidden">Zeugwart</div>
                                <button
                                    type="button"
                                    wire:click="toggleZeugwart({{ $user->id }})"
                                    class="inline-flex w-full justify-center rounded-full px-3 py-2 text-xs font-semibold md:w-auto md:py-1
                                    {{ $user->hasRole('zeugwart') ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-500' }}"
                                >
                                    {{ $user->hasRole('zeugwart') ? 'Zeugwart' : '–' }}
                                </button>
                            </td>
                        @endif

                        <td class="col-span-2 sm:col-span-4 md:table-cell md:px-6 md:py-4 md:text-right">
                            <button
                                type="button"
                                wire:click="resendInvite({{ $user->id }})"
                                wire:confirm="Link zum Passwort-Zurücksetzen erneut an {{ $user->email }} verschicken?"
                                class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium text-slate-600 hover:text-slate-800 md:w-auto md:border-0 md:p-0 md:text-slate-500"
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
