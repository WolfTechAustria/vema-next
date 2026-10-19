<div class="space-y-6">

    @include('partials.flash-messages')

    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">

        <div>
            <h2 class="text-2xl font-bold tracking-tight">
                Willkommen bei VEMA
            </h2>

            <p class="mt-1 text-sm text-slate-500">
                @if($editing)
                    Module an <span class="font-medium">⠿</span> ziehen, um sie anzuordnen.
                @else
                    Übersicht über den aktuellen Vereinsstatus.
                @endif
            </p>
        </div>

        <div class="flex shrink-0 flex-wrap gap-2">
            @if($editing)
                <button type="button" wire:click="resetToDefault" wire:confirm="Eigene Anordnung verwerfen und den Standard verwenden?" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                    Auf Standard zurücksetzen
                </button>

                @if($isAdmin)
                    <button type="button" wire:click="saveAsClubDefault" wire:confirm="Diese Anordnung als Standard für alle Benutzer ohne eigene Anordnung speichern?" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                        Als Vereinsstandard speichern
                    </button>
                @endif

                <button type="button" wire:click="stopEditing" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white">
                    Fertig
                </button>
            @else
                <button type="button" wire:click="startEditing" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                    Dashboard anpassen
                </button>
            @endif
        </div>

    </div>

    {{-- Eigener Key je Modus: so wird die Sortierung nur im Bearbeiten-Modus initialisiert. --}}
    <div
        wire:key="dashboard-modules-{{ $editing ? 'edit' : 'view' }}"
        @if($editing) wire:sort="sortModule" @endif
        class="grid gap-6 lg:grid-cols-2"
    >
        @forelse($modules as $module)

            <section
                wire:key="module-{{ $module->value }}"
                wire:sort:item="{{ $module->value }}"
                class="flex flex-col rounded-xl border bg-white shadow-sm {{ $module->isWide() ? 'lg:col-span-2' : '' }} {{ $editing ? 'border-dashed border-slate-400' : 'border-slate-200' }}"
            >
                <div class="flex items-center justify-between gap-3 border-b border-slate-200 px-5 py-3">
                    <div class="flex min-w-0 items-center gap-2">
                        @if($editing)
                            <span wire:sort:handle class="cursor-grab select-none text-lg leading-none text-slate-400 hover:text-slate-700" title="Ziehen zum Verschieben">⠿</span>
                        @endif

                        <h3 class="truncate font-semibold text-slate-900">
                            {{ $module->label() }}
                        </h3>
                    </div>

                    @if($editing)
                        <button type="button" wire:click="hideModule('{{ $module->value }}')" class="shrink-0 text-sm font-medium text-slate-500 hover:text-red-700">
                            Ausblenden
                        </button>
                    @endif
                </div>

                <div class="flex-1 p-5">
                    @include('livewire.dashboard.modules.'.str_replace('_', '-', $module->value), ['data' => $moduleData[$module->value]])
                </div>
            </section>

        @empty

            <div class="rounded-xl border border-dashed border-slate-300 p-10 text-center text-sm text-slate-500 lg:col-span-2">
                Alle Module sind ausgeblendet. Über <span class="font-medium">Dashboard anpassen</span> können Sie wieder welche hinzufügen.
            </div>

        @endforelse
    </div>

    @if($editing && $hiddenModules !== [])

        <section class="rounded-xl border border-slate-200 bg-white shadow-sm">

            <div class="border-b border-slate-200 px-5 py-3">
                <h3 class="font-semibold text-slate-900">Weitere Module</h3>
            </div>

            <div class="divide-y divide-slate-100">
                @foreach($hiddenModules as $module)
                    <div wire:key="hidden-{{ $module->value }}" class="flex items-center justify-between gap-4 px-5 py-3">
                        <div class="min-w-0">
                            <div class="text-sm font-medium text-slate-900">{{ $module->label() }}</div>
                            <div class="text-xs text-slate-500">{{ $module->description() }}</div>
                        </div>

                        <button type="button" wire:click="showModule('{{ $module->value }}')" class="shrink-0 rounded-lg border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50">
                            Hinzufügen
                        </button>
                    </div>
                @endforeach
            </div>

        </section>

    @endif

</div>
