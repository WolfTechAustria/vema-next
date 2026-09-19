<section class="rounded-xl border border-slate-200 bg-white shadow-sm">

    <div class="flex items-center justify-between border-b border-slate-200 px-6 py-4">

        <div>
            <h3 class="font-semibold text-slate-900">
                Dienstbezeichnungen
            </h3>

            <p class="mt-1 text-sm text-slate-500">
                Lege fest, welche Bereiche/Dienste es in diesem Dienstplan gibt, an welchen
                Wochentagen sie stattfinden und wie viele Helfer benötigt werden. Mehrere
                Dienstbezeichnungen können am selben Tag aktiv sein (z. B. Bar, Standaufsicht,
                Auswertung bei einer Veranstaltung).
            </p>
        </div>

        @unless($showForm)
            <button
                type="button"
                wire:click="newRole"
                class="shrink-0 rounded-lg bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white"
            >
                + Neue Dienstbezeichnung
            </button>
        @endunless

    </div>

    @if($showForm)

        <div class="border-b border-slate-200 bg-slate-50 p-6">

            <h4 class="mb-4 font-semibold text-slate-900">
                {{ $editingRoleID ? 'Dienstbezeichnung bearbeiten' : 'Neue Dienstbezeichnung' }}
            </h4>

            <div class="grid gap-4 md:grid-cols-2">

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">
                        Bezeichnung
                    </label>

                    <input
                        type="text"
                        wire:model="name"
                        placeholder="z. B. Bar, Standaufsicht, Auswertung, Training ..."
                        class="w-full rounded-lg border border-slate-300 px-3 py-2"
                    >

                    @error('name')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">
                        Benötigte Helfer
                    </label>

                    <input
                        type="number"
                        min="1"
                        max="20"
                        wire:model="required_helpers"
                        class="w-full rounded-lg border border-slate-300 px-3 py-2"
                    >

                    @error('required_helpers')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">
                        Beginn (optional)
                    </label>

                    <input
                        type="time"
                        wire:model="start_time"
                        class="w-full rounded-lg border border-slate-300 px-3 py-2"
                    >

                    @error('start_time')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">
                        Ende (optional)
                    </label>

                    <input
                        type="time"
                        wire:model="end_time"
                        class="w-full rounded-lg border border-slate-300 px-3 py-2"
                    >

                    @error('end_time')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">
                        Pflichtgruppe (optional)
                    </label>

                    <select
                        wire:model="requiredGroupID"
                        class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2"
                    >
                        <option value="">Keine</option>

                        @foreach($recipientGroups as $group)
                            <option value="{{ $group->groupID }}">{{ $group->name }}</option>
                        @endforeach
                    </select>

                    <p class="mt-1 text-xs text-slate-500">
                        Bei der automatischen Einteilung wird mindestens ein Helfer aus dieser
                        Gruppe reserviert.
                    </p>
                </div>

                @if($requiredGroupID)
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">
                            Mindestanzahl aus der Gruppe
                        </label>

                        <input
                            type="number"
                            min="1"
                            wire:model="required_group_min"
                            class="w-full rounded-lg border border-slate-300 px-3 py-2"
                        >

                        @error('required_group_min')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                @endif

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">
                        Pflicht-Fähigkeit (optional)
                    </label>

                    <select
                        wire:model="requiredSkillID"
                        class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2"
                    >
                        <option value="">Keine</option>

                        @foreach($skills as $skill)
                            <option value="{{ $skill->skillID }}">{{ $skill->name }}</option>
                        @endforeach
                    </select>

                    <p class="mt-1 text-xs text-slate-500">
                        Nur Helfer mit dieser Fähigkeit kommen für diese Dienstbezeichnung in Frage.
                    </p>
                </div>

            </div>

            <div class="mt-4">
                <label class="mb-2 block text-sm font-medium text-slate-700">
                    Wochentage
                </label>

                <div class="flex flex-wrap gap-3">
                    @foreach([1 => 'Mo', 2 => 'Di', 3 => 'Mi', 4 => 'Do', 5 => 'Fr', 6 => 'Sa', 7 => 'So'] as $weekday => $label)
                        <label class="flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-2">
                            <input
                                type="checkbox"
                                wire:model="weekdays"
                                value="{{ $weekday }}"
                                class="rounded border-slate-300"
                            >
                            <span class="text-sm font-medium text-slate-700">{{ $label }}</span>
                        </label>
                    @endforeach
                </div>

                @error('weekdays')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="mt-5 flex justify-end gap-2">
                <button
                    type="button"
                    wire:click="cancelEdit"
                    class="rounded-lg border border-slate-300 px-4 py-2"
                >
                    Abbrechen
                </button>

                <button
                    type="button"
                    wire:click="saveRole"
                    class="rounded-lg bg-slate-900 px-4 py-2 font-semibold text-white"
                >
                    {{ $editingRoleID ? 'Änderungen speichern' : 'Dienstbezeichnung anlegen' }}
                </button>
            </div>

        </div>

    @endif

    <div class="divide-y divide-slate-100">

        @forelse($roles as $role)

            <div wire:key="role-{{ $role->roleID }}" class="flex items-center justify-between gap-4 p-4">

                <div class="min-w-0">
                    <div class="flex items-center gap-2">
                        <span class="font-medium text-slate-900">
                            {{ $role->name }}
                        </span>

                        @unless($role->active)
                            <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-500">
                                inaktiv
                            </span>
                        @endunless

                        @if($role->requiredGroup)
                            <span class="rounded-full bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-700">
                                ≥{{ $role->required_group_min }} aus {{ $role->requiredGroup->name }}
                            </span>
                        @endif

                        @if($role->requiredSkill)
                            <span class="rounded-full bg-sky-50 px-2 py-0.5 text-xs font-medium text-sky-700">
                                Fähigkeit: {{ $role->requiredSkill->name }}
                            </span>
                        @endif
                    </div>

                    <div class="mt-1 text-sm text-slate-500">
                        {{ $role->required_helpers }} Helfer

                        @if($role->start_time)
                            · {{ substr($role->start_time, 0, 5) }}–{{ substr($role->end_time ?? '', 0, 5) }} Uhr
                        @endif

                        ·
                        @foreach([1 => 'Mo', 2 => 'Di', 3 => 'Mi', 4 => 'Do', 5 => 'Fr', 6 => 'Sa', 7 => 'So'] as $weekday => $label)
                            @if($role->isActiveOnWeekday($weekday))
                                <span class="font-medium">{{ $label }}</span>
                            @else
                                <span class="text-slate-300">{{ $label }}</span>
                            @endif
                        @endforeach
                    </div>
                </div>

                <div class="flex shrink-0 items-center gap-2">

                    <button type="button" wire:click="moveRoleUp({{ $role->roleID }})" class="text-slate-400 hover:text-slate-700" title="Nach oben">
                        ↑
                    </button>

                    <button type="button" wire:click="moveRoleDown({{ $role->roleID }})" class="text-slate-400 hover:text-slate-700" title="Nach unten">
                        ↓
                    </button>

                    <button type="button" wire:click="toggleRoleActive({{ $role->roleID }})" class="text-sm font-medium text-slate-600 hover:text-slate-900">
                        {{ $role->active ? 'Deaktivieren' : 'Aktivieren' }}
                    </button>

                    <button type="button" wire:click="editRole({{ $role->roleID }})" class="text-sm font-medium text-slate-600 hover:text-slate-900">
                        Bearbeiten
                    </button>

                    <button type="button" wire:click="deleteRole({{ $role->roleID }})" wire:confirm="Dienstbezeichnung wirklich löschen?" class="text-sm font-medium text-red-600 hover:text-red-800">
                        Löschen
                    </button>

                </div>

            </div>

        @empty

            <div class="px-6 py-8 text-center text-sm text-slate-500">
                Noch keine Dienstbezeichnungen für diesen Dienstplan angelegt.
            </div>

        @endforelse

    </div>

</section>
