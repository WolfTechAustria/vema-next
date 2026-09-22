<div class="max-w-5xl">

    <div class="mb-6 flex items-center justify-between">
        <div>
            <h2 class="text-2xl font-bold tracking-tight">
                {{ $member->full_name }}
            </h2>

            <p class="mt-1 text-sm text-slate-500">
                Mitglied #{{ $member->memberID }} bearbeiten
            </p>
        </div>

        <a
            href="{{ route('members.show', $member) }}"
            wire:navigate
            class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
        >
            Abbrechen
        </a>
    </div>

    <form wire:submit="save" class="space-y-6">

        <section class="rounded-xl border border-slate-200 bg-white shadow-sm">

            <div class="border-b border-slate-200 px-6 py-4">
                <h3 class="font-semibold text-slate-900">
                    Stammdaten
                </h3>
            </div>

            <div class="grid gap-6 p-6 sm:grid-cols-2">

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
                    <div class="mt-1 text-sm text-red-600">{{ $message }}</div>
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
                    <div class="mt-1 text-sm text-red-600">{{ $message }}</div>
                    @enderror
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">
                        Geburtsdatum
                    </label>

                    <input
                        type="date"
                        wire:model="dateOfBirth"
                        class="w-full rounded-lg border border-slate-300 px-3 py-2"
                    >
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">
                        Eintrittsdatum
                    </label>

                    <input
                        type="date"
                        wire:model="dateOfJoin"
                        class="w-full rounded-lg border border-slate-300 px-3 py-2"
                    >
                </div>

            </div>

        </section>


        <section class="rounded-xl border border-slate-200 bg-white shadow-sm">

            <div class="border-b border-slate-200 px-6 py-4">
                <h3 class="font-semibold text-slate-900">
                    Adresse
                </h3>
            </div>

            <div class="grid gap-6 p-6 sm:grid-cols-3">

                <div class="sm:col-span-2">
                    <label class="mb-1 block text-sm font-medium text-slate-700">
                        Straße
                    </label>

                    <input
                        type="text"
                        wire:model="street"
                        class="w-full rounded-lg border border-slate-300 px-3 py-2"
                    >
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">
                        PLZ
                    </label>

                    <input
                        type="text"
                        inputmode="numeric"
                        maxlength="4"
                        wire:model="zip"
                        class="w-full rounded-lg border border-slate-300 px-3 py-2"
                    >

                    @error('zip')
                    <div class="mt-1 text-sm text-red-600">{{ $message }}</div>
                    @enderror
                </div>

            </div>

        </section>


        <section class="rounded-xl border border-slate-200 bg-white shadow-sm">

            <div class="border-b border-slate-200 px-6 py-4">
                <h3 class="font-semibold text-slate-900">
                    Vereinsdaten
                </h3>
            </div>

            <div class="space-y-5 p-6">

                <label class="flex items-center gap-3">
                    <input
                        type="checkbox"
                        wire:model="competitionMember"
                        class="rounded border-slate-300"
                    >

                    <span class="text-sm font-medium text-slate-700">
                        Wettkampfmitglied
                    </span>
                </label>

                <label class="flex items-center gap-3">
                    <input
                        type="checkbox"
                        wire:model="supportingMember"
                        class="rounded border-slate-300"
                    >

                    <span class="text-sm font-medium text-slate-700">
                        Unterstützendes Mitglied
                    </span>
                </label>

            </div>

        </section>

        <section class="rounded-xl border border-slate-200 bg-white shadow-sm">

            <div class="border-b border-slate-200 px-6 py-4">
                <h3 class="font-semibold text-slate-900">
                    Fähigkeiten
                </h3>
            </div>

            <div class="p-6">

                @if($skills->isEmpty())
                    <p class="text-sm text-slate-500">
                        Noch keine Fähigkeiten angelegt.
                        <a href="{{ route('skills.index') }}" wire:navigate class="font-medium text-slate-700 underline">
                            Jetzt anlegen
                        </a>
                    </p>
                @else
                    <div class="grid gap-3 md:grid-cols-2">
                        @foreach($skills as $skill)
                            <label class="flex items-center gap-3">
                                <input
                                    type="checkbox"
                                    wire:model="selectedSkills"
                                    value="{{ $skill->skillID }}"
                                    class="rounded border-slate-300"
                                >

                                <span class="text-sm font-medium text-slate-700">
                                    {{ $skill->name }}
                                </span>
                            </label>
                        @endforeach
                    </div>
                @endif

            </div>

        </section>

        <section class="rounded-xl border border-slate-200 bg-white shadow-sm">

            <div class="flex items-center justify-between border-b border-slate-200 px-6 py-4">

                <div>
                    <h3 class="font-semibold text-slate-900">
                        E-Mail-Adressen
                    </h3>

                    <p class="mt-1 text-sm text-slate-500">
                        E-Mail-Adressen des Mitglieds.
                    </p>
                </div>

                <button
                    type="button"
                    wire:click="addEmail"
                    class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
                >
                    + E-Mail
                </button>

            </div>

            <div class="space-y-4 p-6">

                @forelse($emails as $index => $email)

                    <div
                        wire:key="email-{{ $email['id'] ?? 'new-'.$index }}"
                        class="flex items-start gap-3"
                    >

                        <div class="flex-1">

                            <input
                                type="email"
                                wire:model="emails.{{ $index }}.email"
                                placeholder="name@beispiel.at"
                                class="w-full rounded-lg border border-slate-300 px-3 py-2"
                            >

                            @error("emails.$index.email")
                            <div class="mt-1 text-sm text-red-600">
                                {{ $message }}
                            </div>
                            @enderror

                        </div>

                        <button
                            type="button"
                            wire:click="removeEmail({{ $index }})"
                            class="rounded-lg px-3 py-2 text-sm font-medium text-red-600 hover:bg-red-50"
                        >
                            Entfernen
                        </button>

                    </div>

                @empty

                    <div class="text-sm text-slate-500">
                        Keine E-Mail-Adresse hinterlegt.
                    </div>

                @endforelse

            </div>

        </section>

        <section class="rounded-xl border border-slate-200 bg-white shadow-sm">

            <div class="flex items-center justify-between border-b border-slate-200 px-6 py-4">

                <div>
                    <h3 class="font-semibold text-slate-900">
                        Telefonnummern
                    </h3>

                    <p class="mt-1 text-sm text-slate-500">
                        Telefonnummern und Zuordnung.
                    </p>
                </div>

                <button
                    type="button"
                    wire:click="addPhone"
                    class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
                >
                    + Telefonnummer
                </button>

            </div>

            <div class="space-y-4 p-6">

                @forelse($phones as $index => $phone)

                    <div
                        wire:key="phone-{{ $phone['id'] ?? 'new-'.$index }}"
                        class="grid gap-3 sm:grid-cols-[180px_1fr_auto]"
                    >

                        <div>

                            <select
                                wire:model="phones.{{ $index }}.phoneCategory"
                                class="w-full rounded-lg border border-slate-300 px-3 py-2"
                            >
                                <option value="1">Privat</option>
                                <option value="2">Geschäftlich</option>
                                <option value="3">Mutter</option>
                                <option value="4">Vater</option>
                                <option value="5">Oma</option>
                                <option value="6">Opa</option>
                                <option value="7">Sonstige</option>
                            </select>

                        </div>

                        <div>

                            <input
                                type="text"
                                wire:model="phones.{{ $index }}.phoneNumber"
                                placeholder="+43 ..."
                                class="w-full rounded-lg border border-slate-300 px-3 py-2"
                            >

                            @error("phones.$index.phoneNumber")
                            <div class="mt-1 text-sm text-red-600">
                                {{ $message }}
                            </div>
                            @enderror

                        </div>

                        <button
                            type="button"
                            wire:click="removePhone({{ $index }})"
                            class="rounded-lg px-3 py-2 text-sm font-medium text-red-600 hover:bg-red-50"
                        >
                            Entfernen
                        </button>

                    </div>

                @empty

                    <div class="text-sm text-slate-500">
                        Keine Telefonnummer hinterlegt.
                    </div>

                @endforelse

            </div>

        </section>


        <div class="flex justify-end gap-3">

            <a
                href="{{ route('members.show', $member) }}"
                wire:navigate
                class="rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50"
            >
                Abbrechen
            </a>

            <button
                type="submit"
                class="rounded-lg bg-slate-900 px-5 py-2.5 text-sm font-semibold text-white hover:bg-slate-800"
            >
                Speichern
            </button>

        </div>

    </form>

</div>
