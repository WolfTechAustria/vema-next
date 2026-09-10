<div class="space-y-6">

    {{-- Header --}}
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">

        <div class="flex items-center gap-4">

            <div class="flex h-16 w-16 items-center justify-center rounded-full bg-slate-200 text-xl font-bold text-slate-600">
                {{ mb_strtoupper(mb_substr($member->name ?? '', 0, 1)) }}
                {{ mb_strtoupper(mb_substr($member->surname ?? '', 0, 1)) }}
            </div>

            <div>
                <div class="flex items-center gap-3">

                    <h2 class="text-2xl font-bold tracking-tight">
                        {{ $member->name }} {{ $member->surname }}
                    </h2>

                    @if($member->active)
                        <span class="rounded-full bg-green-50 px-2.5 py-1 text-xs font-semibold text-green-700">
                            Aktiv
                        </span>
                    @else
                        <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600">
                            Inaktiv
                        </span>
                    @endif

                </div>

                <div class="mt-1 text-sm text-slate-500">
                    Mitglied #{{ $member->memberID }}

                    @if($member->tlsbID)
                        · TLSB {{ $member->tlsbID }}
                    @endif
                </div>
            </div>

        </div>

        <div class="flex gap-2">

            <a
                href="{{ route('members.index') }}"
                wire:navigate
                class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
            >
                Zurück
            </a>

            <a
                href="{{ route('members.edit', $member) }}"
                wire:navigate
                class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800"
            >
                Bearbeiten
            </a>

        </div>

    </div>


    <div class="grid gap-6 xl:grid-cols-3">

        {{-- Stammdaten --}}
        <div class="space-y-6 xl:col-span-2">

            <section class="rounded-xl border border-slate-200 bg-white shadow-sm">

                <div class="border-b border-slate-200 px-6 py-4">
                    <h3 class="font-semibold text-slate-900">
                        Stammdaten
                    </h3>
                </div>

                <div class="grid gap-6 p-6 sm:grid-cols-2">

                    <div>
                        <div class="text-xs font-medium uppercase tracking-wide text-slate-400">
                            Anrede
                        </div>

                        <div class="mt-1 text-sm font-medium text-slate-900">
                            {{ $member->gender ?: '–' }}
                        </div>
                    </div>

                    <div>
                        <div class="text-xs font-medium uppercase tracking-wide text-slate-400">
                            Geburtsdatum
                        </div>

                        <div class="mt-1 text-sm font-medium text-slate-900">
                            {{ $member->dateOfBirth?->format('d.m.Y') ?? '–' }}
                        </div>
                    </div>

                    <div>
                        <div class="text-xs font-medium uppercase tracking-wide text-slate-400">
                            Eintritt
                        </div>

                        <div class="mt-1 text-sm font-medium text-slate-900">
                            {{ $member->dateOfJoin?->format('d.m.Y') ?? '–' }}
                        </div>
                    </div>

                    <div>
                        <div class="text-xs font-medium uppercase tracking-wide text-slate-400">
                            TLSB Nummer
                        </div>

                        <div class="mt-1 text-sm font-medium text-slate-900">
                            {{ $member->tlsbID ?: '–' }}
                        </div>
                    </div>

                    <div>
                        <div class="text-xs font-medium uppercase tracking-wide text-slate-400">
                            Wettkampfmitglied
                        </div>

                        <div class="mt-1 text-sm font-medium text-slate-900">
                            {{ $member->competitionMember ? 'Ja' : 'Nein' }}
                        </div>
                    </div>

                    <div>
                        <div class="text-xs font-medium uppercase tracking-wide text-slate-400">
                            Unterstützendes Mitglied
                        </div>

                        <div class="mt-1 text-sm font-medium text-slate-900">
                            {{ $member->supportingMember ? 'Ja' : 'Nein' }}
                        </div>
                    </div>

                    @if(!$member->active)

                        <div>
                            <div class="text-xs font-medium uppercase tracking-wide text-slate-400">
                                Mitgliedschaft beendet
                            </div>

                            <div class="mt-1 text-sm font-medium text-slate-900">
                                {{ $member->deactiveSince?->format('d.m.Y') ?? '–' }}
                            </div>
                        </div>

                    @endif

                </div>

            </section>


            {{-- Adresse --}}
            <section class="rounded-xl border border-slate-200 bg-white shadow-sm">

                <div class="border-b border-slate-200 px-6 py-4">
                    <h3 class="font-semibold text-slate-900">
                        Adresse
                    </h3>
                </div>

                <div class="p-6">

                    <div class="text-sm leading-6 text-slate-700">

                        @if($member->street)
                            <div>{{ $member->street }}</div>
                        @endif

                        <div>
                            {{ $member->zip }}
                            {{ $member->city?->city }}
                        </div>

                    </div>

                </div>

            </section>

        </div>


        {{-- rechte Seite --}}
        <div class="space-y-6">

            {{-- Kontakt --}}
            <section class="rounded-xl border border-slate-200 bg-white shadow-sm">

                <div class="border-b border-slate-200 px-6 py-4">
                    <h3 class="font-semibold text-slate-900">
                        Kontakt
                    </h3>
                </div>

                <div class="divide-y divide-slate-100">

                    {{-- Emails --}}
                    <div class="p-6">

                        <div class="mb-3 text-xs font-medium uppercase tracking-wide text-slate-400">
                            E-Mail
                        </div>

                        <div class="space-y-2">

                            @forelse($member->emails as $email)

                                <a
                                    href="mailto:{{ $email->email }}"
                                    class="block text-sm font-medium text-blue-600 hover:text-blue-800"
                                >
                                    {{ $email->email }}
                                </a>

                            @empty

                                <div class="text-sm text-slate-400">
                                    Keine E-Mail-Adresse hinterlegt.
                                </div>

                            @endforelse

                        </div>

                    </div>


                    {{-- Telefon --}}
                    <div class="p-6">

                        <div class="mb-3 text-xs font-medium uppercase tracking-wide text-slate-400">
                            Telefon
                        </div>

                        <div class="space-y-3">

                            @forelse($member->phones as $phone)

                                <div>

                                    <div class="text-xs text-slate-400">
                                        {{ $phone->category_name }}
                                    </div>

                                    <a
                                        href="tel:{{ $phone->phoneNumber }}"
                                        class="text-sm font-medium text-slate-900 hover:text-blue-600"
                                    >
                                        {{ $phone->phoneNumber }}
                                    </a>

                                </div>

                            @empty

                                <div class="text-sm text-slate-400">
                                    Keine Telefonnummer hinterlegt.
                                </div>

                            @endforelse

                        </div>

                    </div>

                </div>

            </section>


            {{-- Vereinsdaten --}}
            <section class="rounded-xl border border-slate-200 bg-white shadow-sm">

                <div class="border-b border-slate-200 px-6 py-4">
                    <h3 class="font-semibold text-slate-900">
                        Verein
                    </h3>
                </div>

                <div class="space-y-4 p-6">

                    <div>
                        <div class="text-xs font-medium uppercase tracking-wide text-slate-400">
                            Funktion
                        </div>

                        <div class="mt-1 text-sm font-medium text-slate-900">
                            {{ $member->board_function ?: 'Keine Funktion' }}
                        </div>
                    </div>

                </div>

            </section>

        </div>
    </div>
    <div class="grid gap-6 xl:grid-cols-1">
        <div class="mt-8 rounded-xl border border-slate-200 bg-white shadow-sm">

            <div class="border-b border-slate-200 px-6 py-4">
                <h2 class="text-lg font-semibold text-slate-900">
                    Beitragsvorschreibungen
                </h2>
            </div>

            <div class="p-6">
                @php
                    $prescriptions = $member
                        ->membershipFeePrescriptions()
                        ->with('year')
                        ->latest('sent_at')
                        ->get();
                @endphp

                @if($prescriptions->isEmpty())

                    <p class="text-sm text-slate-500">
                        Noch keine Beitragsvorschreibungen oder Erinnerungen versendet.
                    </p>

                @else

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200">

                            <thead class="bg-slate-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                                    Datum
                                </th>

                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                                    Jahr
                                </th>

                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                                    Typ
                                </th>

                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                                    Versand
                                </th>

                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                                    Empfänger
                                </th>


                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                                    PDF
                                </th>
                            </tr>
                            </thead>

                            <tbody class="divide-y divide-slate-100 bg-white">

                            @foreach($prescriptions as $prescription)

                                <tr>

                                    <td class="whitespace-nowrap px-4 py-3 text-sm text-slate-700">
                                        {{ $prescription->sent_at?->format('d.m.Y H:i') }}
                                    </td>

                                    <td class="whitespace-nowrap px-4 py-3 text-sm text-slate-700">
                                        {{ $prescription->year?->year }}
                                    </td>

                                    <td class="whitespace-nowrap px-4 py-3 text-sm">

                                        @if($prescription->type === 'reminder')

                                            <span class="rounded-full bg-amber-100 px-2.5 py-1 text-xs font-medium text-amber-800">
                                            {{ $prescription->reminder_level }}. Erinnerung
                                        </span>

                                        @else

                                            <span class="rounded-full bg-blue-100 px-2.5 py-1 text-xs font-medium text-blue-800">
                                            Vorschreibung
                                        </span>

                                        @endif

                                    </td>

                                    <td class="whitespace-nowrap px-4 py-3 text-sm">
                                        @if($prescription->delivery_method === 'post')

                                            <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-700">
                                                Post
                                            </span>

                                        @else

                                            <span class="rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-medium text-emerald-800">
                                                E-Mail
                                            </span>

                                        @endif
                                    </td>

                                    <td class="px-4 py-3 text-sm text-slate-700">
                                        {{ $prescription->sent_to }}
                                    </td>

                                    <td class="px-4 py-3 text-sm text-slate-700">
                                        <a
                                            href="{{ route(
                                            'membership-fee-prescriptions.pdf',
                                            $prescription->prescriptionID
                                        ) }}"
                                            target="_blank"
                                            class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50"
                                        >
                                            PDF öffnen
                                        </a>
                                    </td>


                                </tr>

                            @endforeach

                            </tbody>
                        </table>
                    </div>

                @endif
            </div>
        </div>

    </div>

</div>
