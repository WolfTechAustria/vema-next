<div class="mx-auto max-w-4xl space-y-6">

    @include('partials.flash-messages')


    <div class="rounded-xl border border-slate-200 bg-white shadow-sm">

        <div class="border-b border-slate-200 px-6 py-4">

            <h2 class="text-lg font-semibold text-slate-900">
                Persönliche Daten
            </h2>

            <p class="mt-1 text-sm text-slate-500">
                Hier kannst du deine eigenen Stammdaten aktualisieren.
            </p>

        </div>


        <div class="space-y-5 p-6">

            <div class="grid gap-5 md:grid-cols-2">

                <div>

                    <label class="mb-1 block text-sm font-medium text-slate-700">
                        Vorname
                    </label>

                    <input
                        type="text"
                        wire:model="name"
                        class="w-full rounded-lg border border-slate-300 px-3 py-2"
                    >

                    @error('name')
                    <p class="mt-1 text-xs text-red-600">
                        {{ $message }}
                    </p>
                    @enderror

                </div>


                <div>

                    <label class="mb-1 block text-sm font-medium text-slate-700">
                        Nachname
                    </label>

                    <input
                        type="text"
                        wire:model="surname"
                        class="w-full rounded-lg border border-slate-300 px-3 py-2"
                    >

                    @error('surname')
                    <p class="mt-1 text-xs text-red-600">
                        {{ $message }}
                    </p>
                    @enderror

                </div>

            </div>


            <div>

                <label class="mb-1 block text-sm font-medium text-slate-700">
                    Straße
                </label>

                <input
                    type="text"
                    wire:model="street"
                    class="w-full rounded-lg border border-slate-300 px-3 py-2"
                >

                @error('street')
                <p class="mt-1 text-xs text-red-600">
                    {{ $message }}
                </p>
                @enderror

            </div>


            <div class="grid gap-5 md:grid-cols-2">

                <div>

                    <label class="mb-1 block text-sm font-medium text-slate-700">
                        PLZ
                    </label>

                    <input
                        type="text"
                        wire:model="zip"
                        class="w-full rounded-lg border border-slate-300 px-3 py-2"
                    >

                    @error('zip')
                    <p class="mt-1 text-xs text-red-600">
                        {{ $message }}
                    </p>
                    @enderror

                </div>


                <div>

                    <label class="mb-1 block text-sm font-medium text-slate-700">
                        Ort
                    </label>

                    <input
                        type="text"
                        value="{{ $member->city?->city }}"
                        disabled
                        class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-slate-500"
                    >

                </div>

            </div>


            <div class="border-t border-slate-200 pt-5">

                <div class="flex items-center justify-between">

                    <div>
                        <div class="text-sm font-medium text-slate-900">
                            Telefonnummern
                        </div>

                        <div class="mt-1 text-xs text-slate-500">
                            Du kannst mehrere Telefonnummern hinterlegen.
                        </div>
                    </div>

                    <button
                        type="button"
                        wire:click="addPhone"
                        class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
                    >
                        + Telefonnummer
                    </button>

                </div>


                <div class="mt-4 space-y-3">

                    @forelse($phones as $index => $phone)

                        <div
                            wire:key="phone-{{ $phone['ID'] ?? 'new-'.$index }}"
                            class="grid gap-3 rounded-lg border border-slate-200 bg-slate-50 p-3 md:grid-cols-[180px_1fr_auto]"
                        >

                            <select
                                wire:model="phones.{{ $index }}.phoneCategory"
                                class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm"
                            >
                                <option value="1">Privat</option>
                                <option value="2">Geschäftlich</option>
                                <option value="3">Mutter</option>
                                <option value="4">Vater</option>
                                <option value="5">Oma</option>
                                <option value="6">Opa</option>
                            </select>


                            <input
                                type="text"
                                wire:model="phones.{{ $index }}.phoneNumber"
                                placeholder="+43 ..."
                                class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm"
                            >


                            <button
                                type="button"
                                wire:click="removePhone({{ $index }})"
                                wire:confirm="Telefonnummer wirklich entfernen?"
                                class="rounded-lg px-3 py-2 text-sm font-medium text-red-600 hover:bg-red-50"
                            >
                                Entfernen
                            </button>

                        </div>

                    @empty

                        <div class="rounded-lg border border-dashed border-slate-300 px-4 py-5 text-center text-sm text-slate-500">
                            Noch keine Telefonnummer hinterlegt.
                        </div>

                    @endforelse

                </div>

            </div>

            <div class="rounded-lg border border-slate-200 bg-slate-50 p-4">

                <div class="text-xs font-semibold uppercase tracking-wide text-slate-400">
                    Login-E-Mail
                </div>

                <div class="mt-1 text-sm font-medium text-slate-700">
                    {{ $account->email }}
                </div>

                <div class="mt-1 text-xs text-slate-500">
                    Die Login-E-Mail wird separat verwaltet.
                </div>

            </div>

        </div>


        <div class="flex justify-end border-t border-slate-200 bg-slate-50 px-6 py-4">

            <button
                type="button"
                wire:click="saveProfile"
                wire:loading.attr="disabled"
                wire:target="saveProfile"
                class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800 disabled:opacity-60"
            >
                <span wire:loading.remove wire:target="saveProfile">
                    Änderungen speichern
                </span>

                <span wire:loading wire:target="saveProfile">
                    Speichern …
                </span>
            </button>

        </div>

    </div>

</div>
