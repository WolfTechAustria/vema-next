<div>

    @include('partials.flash-messages')

    @if($pendingApplications > 0)
        <a
            href="{{ route('members.applications.index') }}"
            wire:navigate
            class="mb-6 flex items-center justify-between gap-3 rounded-xl border border-amber-200 bg-amber-50 px-5 py-3 text-sm text-amber-900 hover:bg-amber-100"
        >
            <span>
                <strong>{{ $pendingApplications }} {{ $pendingApplications === 1 ? 'neue Online-Anmeldung wartet' : 'neue Online-Anmeldungen warten' }}</strong>
                auf die Freigabe.
            </span>
            <span class="font-semibold">Ansehen →</span>
        </a>
    @endif

    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">

        <div>
            <h2 class="text-2xl font-bold tracking-tight">
                Mitglieder
            </h2>

            <p class="mt-1 text-sm text-slate-500">
                Bestehende Mitglieder aus der VEMA-Datenbank.
            </p>
        </div>

        <div class="grid grid-cols-2 gap-2 sm:flex sm:flex-wrap sm:items-center">

        <a
            href="{{ route('members.pdf.overview') }}"
            target="_blank"
            class="inline-flex justify-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
        >
            PDF Mitgliederliste
        </a>

        @if($applicationsEnabled || $pendingApplications > 0)
            <a
                href="{{ route('members.applications.index') }}"
                wire:navigate
                class="inline-flex justify-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
            >
                Online-Anmeldungen
            </a>
        @endif

        <a href="{{ route('members.birthdays') }}"
           target="_blank"
           class="inline-flex justify-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
          >
            Geburtstagsliste
        </a>

        <button
            type="button"
            wire:click="export"
            class="inline-flex justify-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
        >
            Excel
        </button>

        <button
            type="button"
            wire:click="$toggle('showColumnSettings')"
            class="inline-flex justify-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
        >
            Spalten anpassen
        </button>

        <a
            href="{{ route('members.create') }}"
            wire:navigate
            class="col-span-2 rounded-lg bg-slate-900 px-4 py-2.5 text-center text-sm font-semibold text-white shadow-sm hover:bg-slate-800"
        >
            + Mitglied hinzufügen
        </a>

        </div>

    </div>

    @if($showColumnSettings)
        <section class="mb-6 max-w-2xl rounded-xl border border-slate-200 bg-white p-5 shadow-sm">

            <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                <div>
                    <h3 class="font-semibold text-slate-900">Spalten</h3>
                    <p class="text-xs text-slate-500">
                        Der Name steht immer vorne. Deine Auswahl gilt nur für dich{{ $hasPersonalColumns ? '' : ' – derzeit siehst du den Vereinsstandard' }}.
                    </p>
                </div>

                <div class="flex flex-wrap gap-2">
                    @if($hasPersonalColumns)
                        <button type="button" wire:click="resetColumns" class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">
                            Zurücksetzen
                        </button>
                    @endif

                    @if($isAdmin)
                        <button
                            type="button"
                            wire:click="saveColumnsAsClubDefault"
                            wire:confirm="Diese Spalten als Standard für alle Benutzer ohne eigene Auswahl speichern?"
                            class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50"
                        >
                            Als Vereinsstandard speichern
                        </button>
                    @endif
                </div>
            </div>

            <ul class="divide-y divide-slate-100">
                @foreach($columnChoices as $columnKey)
                    @php($isVisible = in_array($columnKey, $visibleColumns, true))

                    <li wire:key="column-{{ $columnKey }}" class="flex items-center justify-between gap-3 py-2">
                        <label class="flex items-center gap-3 text-sm {{ $isVisible ? 'text-slate-900' : 'text-slate-500' }}">
                            <input
                                type="checkbox"
                                @checked($isVisible)
                                wire:click="toggleColumn('{{ $columnKey }}')"
                                class="rounded border-slate-300"
                            >
                            {{ $columnLabels[$columnKey] }}
                        </label>

                        @if($isVisible)
                            <div class="flex gap-2">
                                <button type="button" wire:click="moveColumn('{{ $columnKey }}', -1)" class="px-1 text-slate-400 hover:text-slate-700" title="Nach links">↑</button>
                                <button type="button" wire:click="moveColumn('{{ $columnKey }}', 1)" class="px-1 text-slate-400 hover:text-slate-700" title="Nach rechts">↓</button>
                            </div>
                        @endif
                    </li>
                @endforeach
            </ul>

        </section>
    @endif

    <div class="rounded-xl border border-slate-200 bg-white shadow-sm">

        <div class="flex flex-col gap-4 border-b border-slate-200 p-4 sm:flex-row">

            <div class="flex-1">
                <input
                    type="search"
                    wire:model.live.debounce.300ms="search"
                    placeholder="Mitglied suchen ..."
                    class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-base focus:border-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-200 sm:py-2 sm:text-sm"
                >
            </div>

            <div>
                <select
                    wire:model.live="status"
                    class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-base focus:border-slate-500 focus:outline-none sm:w-auto sm:py-2 sm:text-sm"
                >
                    <option value="active">Aktive Mitglieder</option>
                    <option value="inactive">Inaktive Mitglieder</option>
                    <option value="all">Alle Mitglieder</option>
                </select>
            </div>

            @if($honorOptions->isNotEmpty())
                <div>
                    <select
                        wire:model.live="honorFilter"
                        class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-base focus:border-slate-500 focus:outline-none sm:w-auto sm:py-2 sm:text-sm"
                    >
                        <option value="">Alle Ehrungen</option>
                        @foreach($honorOptions as $honorOption)
                            <option value="{{ $honorOption->honorID }}">{{ $honorOption->name }}</option>
                        @endforeach
                    </select>
                </div>
            @endif

            @if($filterableFields->isNotEmpty())
                <div class="flex gap-2">
                    <select
                        wire:model.live="fieldFilter"
                        class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-base focus:border-slate-500 focus:outline-none sm:w-auto sm:py-2 sm:text-sm"
                    >
                        <option value="">Filter: Feld …</option>
                        @foreach($filterableFields as $filterableField)
                            <option value="{{ $filterableField->fieldID }}">{{ $filterableField->name }}</option>
                        @endforeach
                    </select>

                    @if($filterField)
                        @if($filterField->type->hasOptions() || $filterField->type === \App\Enums\MemberFieldType::Boolean)
                            <select
                                wire:model.live="fieldFilterValue"
                                class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-base focus:border-slate-500 focus:outline-none sm:w-auto sm:py-2 sm:text-sm"
                            >
                                <option value="">alle</option>
                                @if($filterField->type === \App\Enums\MemberFieldType::Boolean)
                                    <option value="1">Ja</option>
                                    <option value="0">Nein</option>
                                @else
                                    @foreach($filterField->options ?? [] as $option)
                                        <option value="{{ $option }}">{{ $option }}</option>
                                    @endforeach
                                @endif
                            </select>
                        @else
                            <input
                                type="search"
                                wire:model.live.debounce.300ms="fieldFilterValue"
                                placeholder="enthält …"
                                class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-base focus:border-slate-500 focus:outline-none sm:w-40 sm:py-2 sm:text-sm"
                            >
                        @endif
                    @endif
                </div>
            @endif

        </div>

        <div class="overflow-x-auto">

            <table class="block min-w-full md:table md:divide-y md:divide-slate-200">

                <thead class="hidden bg-slate-50 md:table-header-group">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                        Mitglied
                    </th>

                    @foreach($visibleColumns as $columnKey)
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                            {{ $columnLabels[$columnKey] }}
                        </th>
                    @endforeach

                    <th class="px-6 py-3"></th>
                </tr>
                </thead>

                <tbody class="block divide-y divide-slate-100 bg-white md:table-row-group">

                @forelse($members as $member)

                    <tr
                        wire:key="member-row-{{ $member->memberID }}"
                        class="relative flex flex-wrap items-center gap-x-3 gap-y-1 px-4 py-3 hover:bg-slate-50 active:bg-slate-100 md:table-row md:p-0"
                    >

                        <td class="order-1 min-w-0 flex-1 md:order-none md:table-cell md:whitespace-nowrap md:px-6 md:py-4">
                            {{-- Am Handy ist die ganze Karte über diesen Link antippbar --}}
                            <a
                                href="{{ route('members.show', $member) }}"
                                wire:navigate
                                class="block truncate font-medium text-slate-900 after:absolute after:inset-0 hover:text-blue-600 md:after:hidden"
                            >
                                {{ $member->surname }} {{ $member->name }}
                            </a>

                            <div class="text-sm text-slate-500">
                                #{{ $member->memberID }}
                                <span class="md:hidden">
                                    · Eintritt {{ $member->dateOfJoin?->format('d.m.Y') ?? '–' }}
                                </span>
                            </div>
                        </td>

                        @foreach($visibleColumns as $columnKey)
                            @if($columnKey === 'status')
                                <td class="order-2 md:order-none md:table-cell md:whitespace-nowrap md:px-6 md:py-4">

                                    @if($member->active)
                                        <span class="rounded-full bg-green-50 px-2.5 py-1 text-xs font-medium text-green-700">
                                            Aktiv
                                        </span>
                                    @else
                                        <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-600">
                                            Inaktiv
                                        </span>
                                    @endif

                                </td>
                            @else
                                <td class="hidden px-6 py-4 text-sm text-slate-600 md:table-cell">
                                    {{ $columns->value($member, $columnKey) ?? '–' }}
                                </td>
                            @endif
                        @endforeach

                        <td class="order-3 text-lg text-slate-400 md:hidden" aria-hidden="true">
                            ›
                        </td>

                        <td class="hidden whitespace-nowrap px-6 py-4 text-right md:table-cell">

                            <a
                                href="{{ route('members.show', $member) }}"
                                wire:navigate
                                class="inline-flex items-center rounded-lg px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100 hover:text-slate-950"
                            >
                                Öffnen →
                            </a>

                        </td>

                    </tr>

                @empty

                    <tr class="block md:table-row">
                        <td
                            colspan="{{ count($visibleColumns) + 2 }}"
                            class="block px-6 py-12 text-center text-sm text-slate-500 md:table-cell"
                        >
                            Keine Mitglieder gefunden.
                        </td>
                    </tr>

                @endforelse

                </tbody>

            </table>

        </div>

        <div class="border-t border-slate-200 p-4">
            {{ $members->links() }}
        </div>

    </div>

</div>
