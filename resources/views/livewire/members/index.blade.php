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

        <a
            href="{{ route('members.pdf.overview') }}"
            target="_blank"
            class="inline-flex rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
        >
            PDF Mitgliederliste
        </a>

        <a href="{{ route('members.birthdays') }}"
           target="_blank"
           class="inline-flex rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
          >
            Geburtstagsliste
        </a>

        <a
            href="{{ route('members.create') }}"
            wire:navigate
            class="rounded-lg bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-slate-800"
        >
            + Mitglied hinzufügen
        </a>

    </div>

    <div class="rounded-xl border border-slate-200 bg-white shadow-sm">

        <div class="flex flex-col gap-4 border-b border-slate-200 p-4 sm:flex-row">

            <div class="flex-1">
                <input
                    type="search"
                    wire:model.live.debounce.300ms="search"
                    placeholder="Mitglied suchen ..."
                    class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-200"
                >
            </div>

            <div>
                <select
                    wire:model.live="status"
                    class="rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none"
                >
                    <option value="active">Aktive Mitglieder</option>
                    <option value="inactive">Inaktive Mitglieder</option>
                    <option value="all">Alle Mitglieder</option>
                </select>
            </div>

        </div>

        <div class="overflow-x-auto">

            <table class="min-w-full divide-y divide-slate-200">

                <thead class="bg-slate-50">
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

                <tbody class="divide-y divide-slate-100 bg-white">

                @forelse($members as $member)

                    <tr class="hover:bg-slate-50">

                        <td class="whitespace-nowrap px-6 py-4">
                            <a
                                href="{{ route('members.show', $member) }}"
                                wire:navigate
                                class="font-medium text-slate-900 hover:text-blue-600"
                            >
                                {{ $member->surname }} {{ $member->name }}
                            </a>

                            <div class="text-sm text-slate-500">
                                #{{ $member->memberID }}
                            </div>
                        </td>

                        <td class="whitespace-nowrap px-6 py-4">

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

                        <td class="whitespace-nowrap px-6 py-4 text-sm text-slate-600">
                            {{ $member->dateOfJoin?->format('d.m.Y') ?? '–' }}
                        </td>

                        <td class="whitespace-nowrap px-6 py-4 text-right">

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

                    <tr>
                        <td
                            colspan="4"
                            class="px-6 py-12 text-center text-sm text-slate-500"
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
