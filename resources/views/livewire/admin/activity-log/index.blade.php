<div class="space-y-6">

    @include('partials.flash-messages')

    @include('partials.admin-settings-nav')

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
            @foreach($areas as $areaKey => $area)
                <option value="{{ $areaKey }}">{{ $area['label'] }}</option>
            @endforeach
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
                    <tr class="align-top hover:bg-slate-50" wire:key="activity-{{ $entry->activityLogID }}">

                        <td class="whitespace-nowrap px-6 py-4 text-sm text-slate-600">
                            {{ $entry->created_at->format('d.m.Y H:i') }}
                        </td>

                        <td class="whitespace-nowrap px-6 py-4 text-sm font-medium text-slate-900">
                            {{ $entry->actor_name }}
                        </td>

                        <td class="px-6 py-4 text-sm text-slate-700" x-data="{ open: false }">
                            <div>{{ $entry->description }}</div>

                            @if(! empty($entry->changes))
                                <button
                                    type="button"
                                    @click="open = !open"
                                    :aria-expanded="open"
                                    class="mt-1 inline-flex items-center gap-1 text-xs font-medium text-slate-500 hover:text-slate-900"
                                >
                                    <span x-text="open ? 'Details ausblenden' : 'Details ({{ count($entry->changes) }})'"></span>
                                    <svg class="h-3 w-3 transition-transform" :class="open ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                    </svg>
                                </button>

                                <dl x-show="open" x-cloak class="mt-2 space-y-1 rounded-lg bg-slate-50 p-3 text-xs">
                                    @foreach($entry->changes as $change)
                                        <div class="grid gap-x-3 sm:grid-cols-[minmax(0,10rem)_1fr]">
                                            <dt class="font-medium text-slate-600">{{ $change['field'] }}</dt>
                                            <dd class="break-words text-slate-700">
                                                @if($change['masked'] ?? false)
                                                    <span class="italic text-slate-500">geändert</span>
                                                @elseif($change['old'] === null)
                                                    <span class="text-emerald-700">{{ $change['new'] }}</span>
                                                @elseif($change['new'] === null)
                                                    <span class="text-red-700 line-through decoration-red-300">{{ $change['old'] }}</span>
                                                    <span class="text-slate-400">(entfernt)</span>
                                                @else
                                                    <span class="text-red-700 line-through decoration-red-300">{{ $change['old'] }}</span>
                                                    <span class="text-slate-400">→</span>
                                                    <span class="text-emerald-700">{{ $change['new'] }}</span>
                                                @endif
                                            </dd>
                                        </div>
                                    @endforeach
                                </dl>
                            @endif
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
