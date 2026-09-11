<div class="space-y-6">

    <div>
        <h2 class="text-2xl font-bold tracking-tight">
            Empfängergruppen
        </h2>

        <p class="mt-1 text-sm text-slate-500">
            Mitglieder zu wiederverwendbaren Gruppen zusammenfassen.
        </p>
    </div>

    @if(session('success'))
        <div class="rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
            {{ session('success') }}
        </div>
    @endif

    <div class="grid gap-6 lg:grid-cols-2">

        <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">

            <h3 class="text-lg font-semibold">
                Neue Gruppe
            </h3>

            <div class="mt-4 space-y-4">

                <div>
                    <label class="mb-1 block text-sm font-medium">
                        Name
                    </label>

                    <input
                        type="text"
                        wire:model="name"
                        class="w-full rounded-lg border border-slate-300 px-3 py-2"
                    >
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium">
                        Beschreibung
                    </label>

                    <textarea
                        wire:model="description"
                        rows="3"
                        class="w-full rounded-lg border border-slate-300 px-3 py-2"
                    ></textarea>
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium">
                        Mitglieder
                    </label>

                    <div class="max-h-80 overflow-y-auto rounded-lg border border-slate-200">

                        @foreach($members as $member)
                            <label class="flex items-center gap-3 border-b border-slate-100 px-4 py-2 last:border-b-0">

                                <input
                                    type="checkbox"
                                    wire:model.live="selectedMembers"
                                    value="{{ $member->memberID }}"
                                    class="h-4 w-4 rounded border-slate-300"
                                >

                                <span class="text-sm text-slate-700">
                                    {{ $member->surname }}
                                    {{ $member->name }}
                                </span>

                            </label>
                        @endforeach

                    </div>
                </div>

                <button
                    type="button"
                    wire:click="createGroup"
                    class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white"
                >
                    Gruppe anlegen
                </button>

            </div>

        </section>

        <section class="rounded-xl border border-slate-200 bg-white shadow-sm">

            <div class="border-b border-slate-200 px-6 py-4">
                <h3 class="font-semibold">
                    Bestehende Gruppen
                </h3>
            </div>

            <div class="divide-y divide-slate-100">

                @forelse($groups as $group)

                    <div class="flex items-start justify-between gap-4 px-6 py-4">

                        <div>
                            <div class="font-medium text-slate-900">
                                {{ $group->name }}
                            </div>

                            @if($group->description)
                                <div class="mt-1 text-sm text-slate-500">
                                    {{ $group->description }}
                                </div>
                            @endif

                            <div class="mt-2 text-xs text-slate-500">
                                {{ $group->members->count() }}
                                Mitglieder
                            </div>
                        </div>

                        <button
                            type="button"
                            wire:click="deleteGroup({{ $group->groupID }})"
                            wire:confirm="Empfängergruppe wirklich löschen?"
                            class="text-sm font-medium text-red-600 hover:text-red-800"
                        >
                            Löschen
                        </button>

                    </div>

                @empty

                    <div class="px-6 py-8 text-center text-sm text-slate-500">
                        Noch keine Empfängergruppen vorhanden.
                    </div>

                @endforelse

            </div>

        </section>

    </div>

</div>
