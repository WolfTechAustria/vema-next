<div>

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

        <a href="{{ route('members.birthdays') }}"
           target="_blank"
           class="inline-flex justify-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
          >
            Geburtstagsliste
        </a>

        <a
            href="{{ route('members.create') }}"
            wire:navigate
            class="col-span-2 rounded-lg bg-slate-900 px-4 py-2.5 text-center text-sm font-semibold text-white shadow-sm hover:bg-slate-800"
        >
            + Mitglied hinzufügen
        </a>

        </div>

    </div>

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

        </div>

        <div class="overflow-x-auto">

            <table class="block min-w-full md:table md:divide-y md:divide-slate-200">

                <thead class="hidden bg-slate-50 md:table-header-group">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                        Mitglied
                    </th>

                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                        Status
                    </th>

                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                        Eintritt
                    </th>

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

                        <td class="hidden whitespace-nowrap px-6 py-4 text-sm text-slate-600 md:table-cell">
                            {{ $member->dateOfJoin?->format('d.m.Y') ?? '–' }}
                        </td>

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
                            colspan="4"
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
