<div class="space-y-6">

    @include('partials.flash-messages')

    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">

        <div>
            <h2 class="text-2xl font-bold tracking-tight">
                Aktivitätsprotokoll
            </h2>

            <p class="mt-1 text-sm text-slate-500">
                Anmeldungen, Änderungen und Neuanlagen im Überblick.
            </p>
        </div>

        <select
            wire:model.live="filter"
            class="rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm"
        >
            <option value="all">Alle Ereignisse</option>
            <option value="login">Anmeldungen</option>
            <option value="member">Mitglieder</option>
            <option value="user">Benutzerverwaltung</option>
            <option value="settings">Einstellungen</option>
        </select>

    </div>

    <section class="rounded-xl border border-slate-200 bg-white shadow-sm">

        <div class="overflow-x-auto">

            <table class="min-w-full divide-y divide-slate-200">

                <thead class="bg-slate-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Zeitpunkt</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Akteur</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Ereignis</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">IP-Adresse</th>
                </tr>
                </thead>

                <tbody class="divide-y divide-slate-100">

                @forelse($entries as $entry)
                    <tr class="hover:bg-slate-50" wire:key="activity-{{ $entry->activityLogID }}">

                        <td class="whitespace-nowrap px-6 py-4 text-sm text-slate-600">
                            {{ $entry->created_at->format('d.m.Y H:i') }}
                        </td>

                        <td class="whitespace-nowrap px-6 py-4 text-sm font-medium text-slate-900">
                            {{ $entry->actor_name }}
                        </td>

                        <td class="px-6 py-4 text-sm text-slate-700">
                            {{ $entry->description }}
                        </td>

                        <td class="whitespace-nowrap px-6 py-4 text-sm text-slate-400">
                            {{ $entry->ip_address ?? '–' }}
                        </td>

                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-6 py-12 text-center text-sm text-slate-500">
                            Noch keine Ereignisse protokolliert.
                        </td>
                    </tr>
                @endforelse

                </tbody>

            </table>

        </div>

        <div class="border-t border-slate-200 px-6 py-4">
            {{ $entries->links() }}
        </div>

    </section>

</div>
