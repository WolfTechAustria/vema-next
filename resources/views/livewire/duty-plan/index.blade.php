@if(session('success'))

    <div class="rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm font-medium text-green-800">
        {{ session('success') }}
    </div>

@endif

@if(session('error'))

    <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-800">
        {{ session('error') }}
    </div>

@endif

@if(session('warning'))

    <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-medium text-amber-800">
        ⚠ {{ session('warning') }}
    </div>

@endif


<div class="space-y-6">

    {{-- Kopf --}}
    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">

        <div>
            <h2 class="text-2xl font-bold tracking-tight">
                Dienstplan
            </h2>

            <p class="mt-1 text-sm text-slate-500">
                Dienste planen, Helfer einteilen und Verfügbarkeiten berücksichtigen.
            </p>
        </div>

        <div class="flex gap-2">

            <a
                href="{{ route('duty-plan.volunteers') }}"
                wire:navigate
                class="rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50"
            >
                Helfer verwalten
            </a>

            <a
                href="{{ route('duty-plan.absences') }}"
                wire:navigate
                class="rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50"
            >
                Abwesenheiten
            </a>

        </div>

    </div>


    {{-- Dienstplan Auswahl --}}
    <section class="rounded-xl border border-slate-200 bg-white shadow-sm">

        <div class="flex flex-col gap-4 p-6 lg:flex-row lg:items-end">

            <div class="flex-1">

                <label class="mb-1 block text-sm font-medium text-slate-700">
                    Dienstplan
                </label>

                <select
                    wire:change="selectPlan($event.target.value)"
                    class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5"
                >

                    @foreach(
                        \App\Models\DutyPlan::orderByDesc('date_from')->get()
                        as $plan
                    )

                        <option
                            value="{{ $plan->planID }}"
                            @selected($plan->planID === $planId)
                        >
                            {{ $plan->name }}

                            ({{ $plan->date_from->format('d.m.Y') }}
                            –
                            {{ $plan->date_to->format('d.m.Y') }})
                        </option>

                    @endforeach

                </select>

            </div>


            <button
                type="button"
                wire:click="$set('showCreatePlan', true)"
                class="rounded-lg bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white"
            >
                + Neuer Dienstplan
            </button>


            <div class="flex flex-wrap gap-2">

                <button
                    type="button"
                    wire:click="savePlan"
                    class="rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50"
                >
                    Speichern
                </button>

                <button
                    type="button"
                    wire:click="duplicatePlan"
                    class="rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50"
                >
                    Duplizieren
                </button>

                <button
                    type="button"
                    wire:click="deletePlan"
                    wire:confirm="Diesen Dienstplan wirklich löschen? Alle Termine und Einteilungen dieses Plans werden entfernt."
                    class="rounded-lg border border-red-200 bg-white px-4 py-2.5 text-sm font-medium text-red-600 hover:bg-red-50"
                >
                    Löschen
                </button>

            </div>

        </div>

    </section>


    {{-- Neuer Dienstplan --}}
    @if($showCreatePlan)

        <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">

            <h3 class="mb-5 font-semibold">
                Neuen Dienstplan erstellen
            </h3>

            <div class="grid gap-4 md:grid-cols-3">

                <div>

                    <label class="mb-1 block text-sm font-medium">
                        Name
                    </label>

                    <input
                        wire:model="planName"
                        type="text"
                        placeholder="z. B. Saison 2027"
                        class="w-full rounded-lg border border-slate-300 px-3 py-2"
                    >

                </div>


                <div>

                    <label class="mb-1 block text-sm font-medium">
                        Von
                    </label>

                    <input
                        wire:model="dateFrom"
                        type="date"
                        class="w-full rounded-lg border border-slate-300 px-3 py-2"
                    >

                </div>


                <div>

                    <label class="mb-1 block text-sm font-medium">
                        Bis
                    </label>

                    <input
                        wire:model="dateTo"
                        type="date"
                        class="w-full rounded-lg border border-slate-300 px-3 py-2"
                    >

                </div>

            </div>

            @if($planId)
                <label class="mt-4 flex items-center gap-3">
                    <input
                        type="checkbox"
                        wire:model="copyFromCurrentPlan"
                        class="rounded border-slate-300"
                    >

                    <span class="text-sm font-medium text-slate-700">
                        Dienstbezeichnungen (und Helferliste) vom aktuell ausgewählten Dienstplan übernehmen
                    </span>
                </label>
            @endif


            <div class="mt-5 flex justify-end gap-2">

                <button
                    type="button"
                    wire:click="$set('showCreatePlan', false)"
                    class="rounded-lg border border-slate-300 px-4 py-2"
                >
                    Abbrechen
                </button>

                <button
                    type="button"
                    wire:click="createPlan"
                    class="rounded-lg bg-slate-900 px-4 py-2 font-semibold text-white"
                >
                    Dienstplan erstellen
                </button>

            </div>

        </section>

    @endif


    {{-- Zeitraum --}}
    <section class="rounded-xl border border-slate-200 bg-white shadow-sm">

        <div class="border-b border-slate-200 px-6 py-4">

            <h3 class="font-semibold text-slate-900">
                Zeitraum
            </h3>

        </div>

        <div class="grid gap-6 p-6 md:grid-cols-3">

            <div>

                <label class="mb-1 block text-sm font-medium text-slate-700">
                    Von
                </label>

                <input
                    type="date"
                    wire:model.live="dateFrom"
                    class="w-full rounded-lg border border-slate-300 px-3 py-2"
                >

            </div>


            <div>

                <label class="mb-1 block text-sm font-medium text-slate-700">
                    Bis
                </label>

                <input
                    type="date"
                    wire:model.live="dateTo"
                    class="w-full rounded-lg border border-slate-300 px-3 py-2"
                >

            </div>


            <div class="flex items-end">

                <label class="flex items-center gap-3 pb-2">

                    <input
                        type="checkbox"
                        wire:model="excludeHolidays"
                        class="rounded border-slate-300"
                    >

                    <span class="text-sm font-medium text-slate-700">
                        Österreichische Feiertage auslassen
                    </span>

                </label>

            </div>

        </div>

    </section>


    {{-- Dienstbezeichnungen --}}
    @if($planId)
        <livewire:duty-plan.roles-editor :plan-id="$planId" :key="'roles-editor-'.$planId" />
    @endif


    {{-- Aktionen --}}
    <div class="flex flex-wrap gap-3">

        <button
            type="button"
            wire:click="generateEvents"
            wire:loading.attr="disabled"
            wire:target="generateEvents"
            class="rounded-lg bg-slate-900 px-5 py-2.5 text-sm font-semibold text-white hover:bg-slate-800 disabled:opacity-50"
        >

            <span wire:loading.remove wire:target="generateEvents">
                Termine erzeugen
            </span>

            <span wire:loading wire:target="generateEvents">
                Termine werden erzeugt ...
            </span>

        </button>


        <button
            type="button"
            wire:click="autoAssign"
            wire:confirm="Bestehende Einteilungen im ausgewählten Zeitraum werden neu erstellt. Fortfahren?"
            wire:loading.attr="disabled"
            wire:target="autoAssign"
            class="rounded-lg border border-slate-300 bg-white px-5 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50 disabled:opacity-50"
        >

            <span wire:loading.remove wire:target="autoAssign">
                Automatisch einteilen
            </span>

            <span wire:loading wire:target="autoAssign">
                Einteilung läuft ...
            </span>

        </button>

    </div>


    {{-- Diensttermine --}}
    <section class="rounded-xl border border-slate-200 bg-white shadow-sm">

        <div class="flex items-center justify-between border-b border-slate-200 px-6 py-4">

            <div>

                <h3 class="font-semibold text-slate-900">
                    Diensttermine
                </h3>

                <p class="mt-1 text-sm text-slate-500">
                    {{ $this->events->count() }} Termine im ausgewählten Zeitraum.
                </p>

            </div>

        </div>


        {{-- Export --}}
        <div class="flex flex-wrap items-end gap-3 p-6">

            <div>

                <label class="mb-1 block text-xs font-medium uppercase tracking-wide text-slate-400">
                    Export
                </label>

                <select
                    wire:model="exportWeekday"
                    class="rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm"
                >

                    <option value="all">
                        Alle Wochentage
                    </option>

                    @for($weekday = 1; $weekday <= 7; $weekday++)

                        <option value="{{ $weekday }}">
                            {{ $this->weekdayName($weekday) }}
                        </option>

                    @endfor

                </select>

            </div>

            <div>

                <label class="mb-1 block text-xs font-medium uppercase tracking-wide text-slate-400">
                    Dienstbezeichnung
                </label>

                <select
                    wire:model="exportRole"
                    class="rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm"
                >

                    <option value="all">
                        Alle Dienstbezeichnungen
                    </option>

                    @foreach($this->exportableRoles as $role)
                        <option value="{{ $role->roleID }}">
                            {{ $role->name }}
                        </option>
                    @endforeach

                </select>

            </div>


            <button
                type="button"
                wire:click="exportExcel"
                wire:loading.attr="disabled"
                wire:target="exportExcel"
                class="rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50"
            >
                Excel exportieren
            </button>


            <button
                type="button"
                wire:click="exportPdf"
                wire:loading.attr="disabled"
                wire:target="exportPdf"
                class="rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50"
            >
                PDF exportieren
            </button>

        </div>


        {{-- Tabelle --}}
        <div class="overflow-x-auto">

            <table class="min-w-full divide-y divide-slate-200">

                <thead class="bg-slate-50">

                <tr>

                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                        Datum
                    </th>

                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                        Dienst
                    </th>

                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                        Helfer
                    </th>

                    <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">
                        Status
                    </th>

                </tr>

                </thead>


                <tbody class="divide-y divide-slate-100">

                @forelse($this->events as $event)

                    <tr class="hover:bg-slate-50">

                        {{-- Datum --}}
                        <td class="whitespace-nowrap px-6 py-4">

                            <div class="font-medium text-slate-900">
                                {{ $event->duty_date->format('d.m.Y') }}
                            </div>

                            <div class="text-sm text-slate-500">
                                {{ $event->duty_date
                                    ->locale('de')
                                    ->translatedFormat('l') }}
                            </div>

                            @if($holiday = $this->holidayName($event->duty_date))

                                <div class="mt-1">

                                    <span class="rounded-full bg-red-50 px-2 py-0.5 text-xs font-medium text-red-700">
                                        {{ $holiday }}
                                    </span>

                                </div>

                            @endif

                        </td>


                        {{-- Dienst --}}
                        <td class="px-6 py-4">

                            <div class="font-medium text-slate-900">
                                {{ $event->duty_name }}
                            </div>

                            <div class="text-sm text-slate-500">
                                {{ $event->required_helpers }}
                                Helfer benötigt

                                @if($event->start_time)
                                    · {{ substr($event->start_time, 0, 5) }}–{{ substr($event->end_time ?? '', 0, 5) }} Uhr
                                @endif
                            </div>

                            <div class="mt-1 flex flex-wrap gap-1">
                                @if($event->role?->requiredGroup)
                                    <span class="rounded-full bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-700">
                                        ≥{{ $event->role->required_group_min }} aus {{ $event->role->requiredGroup->name }}
                                    </span>
                                @endif

                                @if($event->role?->requiredSkill)
                                    <span class="rounded-full bg-sky-50 px-2 py-0.5 text-xs font-medium text-sky-700">
                                        Fähigkeit: {{ $event->role->requiredSkill->name }}
                                    </span>
                                @endif
                            </div>

                        </td>


                        {{-- Helfer --}}
                        <td class="px-6 py-4">

                            <div class="space-y-2">

                                @for($slot = 1; $slot <= $event->required_helpers; $slot++)

                                    @php
                                        $assignment = $event->assignments
                                            ->firstWhere('slot_no', $slot);

                                        $selectedVolunteerKey = null;

                                        if ($assignment?->memberID) {

                                            $selectedVolunteerKey =
                                                'member:' . $assignment->memberID;

                                        } elseif ($assignment?->externalContactID) {

                                            $selectedVolunteerKey =
                                                'external:' . $assignment->externalContactID;

                                        }

                                        $weekday =
                                            $event->duty_date->isoWeekday();

                                        $requiredSkillID = $event->role?->requiredSkillID;
                                    @endphp


                                    <div class="flex items-center gap-2">

                                        <div class="w-16 shrink-0 text-xs font-medium text-slate-400">
                                            Helfer {{ $slot }}
                                        </div>


                                        <select
                                            wire:key="assignment-{{ $event->eventID }}-{{ $slot }}"
                                            wire:change="updateAssignment(
                                                {{ $event->eventID }},
                                                {{ $slot }},
                                                $event.target.value || null
                                            )"
                                            class="min-w-60 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm"
                                        >

                                            <option value="">
                                                Nicht besetzt
                                            </option>


                                            {{-- Vereinsmitglieder --}}
                                            <optgroup label="Mitglieder">

                                                @foreach($volunteers as $volunteer)

                                                    @php
                                                        $available =
                                                            $volunteer->isAvailableOnWeekday($weekday)
                                                            && $this->memberAvailableForEvent(
                                                                $volunteer->memberID,
                                                                $event->duty_date
                                                            )
                                                            && (
                                                                !$requiredSkillID
                                                                || $volunteer->member->skills->contains('skillID', $requiredSkillID)
                                                            );

                                                        $volunteerKey =
                                                            'member:' . $volunteer->memberID;
                                                    @endphp


                                                    @if($available)

                                                        <option
                                                            value="{{ $volunteerKey }}"
                                                            @selected(
                                                                $selectedVolunteerKey === $volunteerKey
                                                            )
                                                        >
                                                            {{ $volunteer->member->surname }}
                                                            {{ $volunteer->member->name }}
                                                        </option>

                                                    @endif

                                                @endforeach

                                            </optgroup>


                                            {{-- Externe Helfer --}}
                                            <optgroup label="Externe Helfer">

                                                @foreach($externalVolunteers as $volunteer)

                                                    @php
                                                        $available =
                                                            $volunteer->isAvailableOnWeekday($weekday)
                                                            && (
                                                                !$requiredSkillID
                                                                || $volunteer->externalContact->skills->contains('skillID', $requiredSkillID)
                                                            );

                                                        $volunteerKey =
                                                            'external:' . $volunteer->externalContactID;
                                                    @endphp


                                                    @if($available)

                                                        <option
                                                            value="{{ $volunteerKey }}"
                                                            @selected(
                                                                $selectedVolunteerKey === $volunteerKey
                                                            )
                                                        >
                                                            {{ $volunteer->externalContact->surname }}
                                                            {{ $volunteer->externalContact->name }}

                                                            @if($volunteer->externalContact->organization)
                                                                – {{ $volunteer->externalContact->organization }}
                                                            @endif
                                                        </option>

                                                    @endif

                                                @endforeach

                                            </optgroup>

                                        </select>

                                    </div>

                                @endfor

                            </div>

                        </td>


                        {{-- Status --}}
                        <td class="whitespace-nowrap px-6 py-4 text-right">

                            @if(
                                $event->assignments->count()
                                >= $event->required_helpers
                            )

                                <span class="rounded-full bg-green-50 px-2.5 py-1 text-xs font-medium text-green-700">
                                    Vollständig
                                </span>

                            @elseif($event->assignments->count() > 0)

                                <span class="rounded-full bg-amber-50 px-2.5 py-1 text-xs font-medium text-amber-700">
                                    Teilweise
                                </span>

                            @else

                                <span class="rounded-full bg-red-50 px-2.5 py-1 text-xs font-medium text-red-700">
                                    Offen
                                </span>

                            @endif

                            @if($warning = $this->eventGroupWarning($event))
                                <div class="mt-1">
                                    <span class="inline-flex rounded-full bg-red-50 px-2.5 py-1 text-xs font-medium text-red-700">
                                        ⚠ {{ $warning }}
                                    </span>
                                </div>
                            @endif

                        </td>

                    </tr>

                @empty

                    <tr>

                        <td
                            colspan="4"
                            class="px-6 py-12 text-center text-sm text-slate-500"
                        >
                            Für diesen Zeitraum wurden noch keine Diensttermine angelegt.
                        </td>

                    </tr>

                @endforelse

                </tbody>

            </table>

        </div>

    </section>

</div>
