<div class="space-y-6">

    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">

        <div>
            <h2 class="text-2xl font-bold tracking-tight">
                Helferverwaltung
            </h2>

            <p class="mt-1 text-sm text-slate-500">
                Lege fest, welche Mitglieder für Dienste verfügbar sind und an welchen Wochentagen sie helfen können.
            </p>
        </div>

        <a
            href="{{ route('duty-plan.index') }}"
            wire:navigate
            class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
        >
            Zurück zum Dienstplan
        </a>

    </div>

    <section class="rounded-xl border border-slate-200 bg-white shadow-sm">

        <div class="border-b border-slate-200 p-4">

            <input
                type="search"
                wire:model.live.debounce.300ms="search"
                placeholder="Mitglied suchen ..."
                class="w-full max-w-md rounded-lg border border-slate-300 px-3 py-2 text-sm"
            >

        </div>

        <div class="overflow-x-auto">

            <table class="min-w-full divide-y divide-slate-200">

                <thead class="bg-slate-50">
                <tr>

                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                        Mitglied
                    </th>

                    <th class="px-6 py-3 text-center text-xs font-semibold uppercase tracking-wide text-slate-500">
                        Helfer
                    </th>

                    @for($weekday = 1; $weekday <= 7; $weekday++)

                        <th class="px-3 py-3 text-center text-xs font-semibold uppercase tracking-wide text-slate-500">
                            {{ $this->weekdayName($weekday) }}
                        </th>

                    @endfor

                </tr>
                </thead>

                <tbody class="divide-y divide-slate-100">

                @foreach($members as $member)

                    @php
                        $volunteer = $member->dutyVolunteer;

                        $active = (bool) ($volunteer?->active ?? false);

                        $mask = (int) ($volunteer?->weekday_mask ?? 127);
                    @endphp

                    <tr class="hover:bg-slate-50">

                        <td class="px-6 py-4">

                            <div class="font-medium text-slate-900">
                                {{ $member->surname }} {{ $member->name }}
                            </div>

                            <div class="text-xs text-slate-400">
                                #{{ $member->memberID }}
                            </div>

                        </td>

                        <td class="px-6 py-4 text-center">

                            <button
                                type="button"
                                wire:click="toggleVolunteer({{ $member->memberID }})"
                                class="
                                        inline-flex rounded-full px-3 py-1 text-xs font-semibold
                                        {{ $active
                                            ? 'bg-green-50 text-green-700'
                                            : 'bg-slate-100 text-slate-500'
                                        }}
                                    "
                            >
                                {{ $active ? 'Aktiv' : 'Inaktiv' }}
                            </button>

                        </td>

                        @for($weekday = 1; $weekday <= 7; $weekday++)

                            @php
                                $bit = 1 << ($weekday - 1);

                                $available = ($mask & $bit) !== 0;
                            @endphp

                            <td class="px-3 py-4 text-center">

                                <button
                                    type="button"
                                    wire:click="toggleWeekday({{ $member->memberID }}, {{ $weekday }})"
                                    @disabled(!$active)
                                    class="
                                            inline-flex h-8 w-8 items-center justify-center rounded-lg border text-sm font-semibold
                                            {{ !$active
                                                ? 'cursor-not-allowed border-slate-200 bg-slate-50 text-slate-300'
                                                : (
                                                    $available
                                                        ? 'border-green-200 bg-green-50 text-green-700'
                                                        : 'border-slate-200 bg-white text-slate-300'
                                                )
                                            }}
                                        "
                                >
                                    {{ $available ? '✓' : '–' }}
                                </button>

                            </td>

                        @endfor

                    </tr>

                @endforeach

                </tbody>

            </table>

        </div>

    </section>

</div>
