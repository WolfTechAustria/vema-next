<div class="space-y-6">

    {{-- Kopf --}}
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">

        <div>
            <h2 class="text-2xl font-bold tracking-tight">
                Abwesenheiten
            </h2>

            <p class="mt-1 text-sm text-slate-500">
                Sperrtermine und Abwesenheiten der Helfer verwalten.
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


    {{-- Erfolgsmeldung --}}
    @if(session('success'))

        <div class="rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm font-medium text-green-800">
            {{ session('success') }}
        </div>

    @endif


    {{-- Neue Abwesenheit --}}
    <section class="rounded-xl border border-slate-200 bg-white shadow-sm">

        <div class="border-b border-slate-200 px-6 py-4">

            <h3 class="font-semibold text-slate-900">
                Abwesenheit eintragen
            </h3>

            <p class="mt-1 text-sm text-slate-500">
                Ein einzelner Tag oder ein kompletter Zeitraum kann gesperrt werden.
            </p>

        </div>


        <form wire:submit="save">

            <div class="grid gap-6 p-6 lg:grid-cols-4">

                {{-- Mitglied --}}
                <div class="lg:col-span-2">

                    <label class="mb-1 block text-sm font-medium text-slate-700">
                        Helfer
                    </label>

                    <select
                        wire:model="memberId"
                        class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm"
                    >
                        <option value="">
                            Bitte auswählen
                        </option>

                        @foreach($volunteers as $volunteer)

                            @if($volunteer->member)

                                <option value="{{ $volunteer->memberID }}">
                                    {{ $volunteer->member->surname }}
                                    {{ $volunteer->member->name }}
                                </option>

                            @endif

                        @endforeach
                    </select>

                    @error('memberId')
                    <div class="mt-1 text-sm text-red-600">
                        {{ $message }}
                    </div>
                    @enderror

                </div>


                {{-- Von --}}
                <div>

                    <label class="mb-1 block text-sm font-medium text-slate-700">
                        Von
                    </label>

                    <input
                        type="date"
                        wire:model="dateFrom"
                        class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm"
                    >

                    @error('dateFrom')
                    <div class="mt-1 text-sm text-red-600">
                        {{ $message }}
                    </div>
                    @enderror

                </div>


                {{-- Bis --}}
                <div>

                    <label class="mb-1 block text-sm font-medium text-slate-700">
                        Bis
                    </label>

                    <input
                        type="date"
                        wire:model="dateTo"
                        class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm"
                    >

                    @error('dateTo')
                    <div class="mt-1 text-sm text-red-600">
                        {{ $message }}
                    </div>
                    @enderror

                </div>


                {{-- Notiz --}}
                <div class="lg:col-span-4">

                    <label class="mb-1 block text-sm font-medium text-slate-700">
                        Notiz
                    </label>

                    <input
                        type="text"
                        wire:model="note"
                        placeholder="z. B. Urlaub, Arbeit, nicht verfügbar ..."
                        class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm"
                    >

                    @error('note')
                    <div class="mt-1 text-sm text-red-600">
                        {{ $message }}
                    </div>
                    @enderror

                </div>

            </div>


            <div class="flex justify-end border-t border-slate-200 bg-slate-50 px-6 py-4">

                <button
                    type="submit"
                    wire:loading.attr="disabled"
                    wire:target="save"
                    class="rounded-lg bg-slate-900 px-5 py-2.5 text-sm font-semibold text-white hover:bg-slate-800 disabled:opacity-50"
                >

                    <span wire:loading.remove wire:target="save">
                        Abwesenheit speichern
                    </span>

                    <span wire:loading wire:target="save">
                        Speichern ...
                    </span>

                </button>

            </div>

        </form>

    </section>


    {{-- Bestehende Abwesenheiten --}}
    <section class="rounded-xl border border-slate-200 bg-white shadow-sm">

        <div class="border-b border-slate-200 px-6 py-4">

            <h3 class="font-semibold text-slate-900">
                Eingetragene Abwesenheiten
            </h3>

            <p class="mt-1 text-sm text-slate-500">
                {{ $absences->count() }} Einträge vorhanden.
            </p>

        </div>


        <div class="overflow-x-auto">

            <table class="min-w-full divide-y divide-slate-200">

                <thead class="bg-slate-50">

                <tr>

                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                        Mitglied
                    </th>

                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                        Zeitraum
                    </th>

                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                        Dauer
                    </th>

                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                        Notiz
                    </th>

                    <th class="px-6 py-3"></th>

                </tr>

                </thead>


                <tbody class="divide-y divide-slate-100">

                @forelse($absences as $absence)

                    <tr
                        wire:key="absence-{{ $absence->absenceID }}"
                        class="hover:bg-slate-50"
                    >

                        {{-- Mitglied --}}
                        <td class="whitespace-nowrap px-6 py-4">

                            <div class="font-medium text-slate-900">
                                {{ $absence->member?->surname }}
                                {{ $absence->member?->name }}
                            </div>

                            <div class="text-xs text-slate-400">
                                #{{ $absence->memberID }}
                            </div>

                        </td>


                        {{-- Zeitraum --}}
                        <td class="whitespace-nowrap px-6 py-4 text-sm text-slate-700">

                            @if($absence->date_from->isSameDay($absence->date_to))

                                {{ $absence->date_from->format('d.m.Y') }}

                            @else

                                {{ $absence->date_from->format('d.m.Y') }}
                                –
                                {{ $absence->date_to->format('d.m.Y') }}

                            @endif

                        </td>


                        {{-- Dauer --}}
                        <td class="whitespace-nowrap px-6 py-4">

                            @php
                                $days = $absence->date_from
                                    ->diffInDays($absence->date_to) + 1;
                            @endphp

                            <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-700">
                                    {{ $days }}
                                {{ $days === 1 ? 'Tag' : 'Tage' }}
                                </span>

                        </td>


                        {{-- Notiz --}}
                        <td class="px-6 py-4 text-sm text-slate-600">
                            {{ $absence->note ?: '–' }}
                        </td>


                        {{-- Löschen --}}
                        <td class="whitespace-nowrap px-6 py-4 text-right">

                            <button
                                type="button"
                                wire:click="delete({{ $absence->absenceID }})"
                                wire:confirm="Diese Abwesenheit wirklich löschen?"
                                class="rounded-lg px-3 py-2 text-sm font-medium text-red-600 hover:bg-red-50"
                            >
                                Löschen
                            </button>

                        </td>

                    </tr>

                @empty

                    <tr>

                        <td
                            colspan="5"
                            class="px-6 py-12 text-center"
                        >

                            <div class="text-sm font-medium text-slate-700">
                                Keine Abwesenheiten eingetragen
                            </div>

                            <div class="mt-1 text-sm text-slate-400">
                                Alle aktiven Helfer stehen aktuell ohne zusätzliche Sperrtermine zur Verfügung.
                            </div>

                        </td>

                    </tr>

                @endforelse

                </tbody>

            </table>

        </div>

    </section>

</div>
