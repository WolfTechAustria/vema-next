<div>

    <div class="mb-8">
        <h2 class="text-2xl font-bold tracking-tight">
            Willkommen bei VEMA
        </h2>

        <p class="mt-1 text-sm text-slate-500">
            Übersicht über den aktuellen Vereinsstatus.
        </p>
    </div>

    <div class="grid gap-6 sm:grid-cols-2 xl:grid-cols-4">

        <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="text-sm font-medium text-slate-500">
                Aktive Mitglieder
            </div>

            <div class="mt-2 text-3xl font-bold">
                {{ $activeMembers }}
            </div>
        </div>

        <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="text-sm font-medium text-slate-500">
                Mitglieder gesamt
            </div>

            <div class="mt-2 text-3xl font-bold">
                {{ $allMembers }}
            </div>
        </div>




        <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="text-sm font-medium text-slate-500">
                Nächster Dienst
            </div>

            <div class="mt-2 text-3xl font-bold text-slate-300">
                –
            </div>

            <div class="mt-1 text-xs text-slate-400">
                folgt mit Dienstplan
            </div>
        </div>

    </div>


    <!-- Beitragsübersicht -->
    <div class="grid gap-6 sm:grid-cols-2 xl:grid-cols-1">
        @php
            $membershipFeeStats = $this->membershipFeeStats();
        @endphp

        @if($membershipFeeStats['year'])

            <div class="mt-8">

                <div class="mb-4 flex items-center justify-between">
                    <div>
                        <h2 class="text-lg font-semibold text-slate-900">
                            Mitgliedsbeiträge {{ $membershipFeeStats['year'] }}
                        </h2>

                        <p class="text-sm text-slate-500">
                            Aktueller Stand der Beitragsvorschreibungen
                        </p>
                    </div>

                    <a
                        href="{{ route('membership-fees.index') }}"
                        class="text-sm font-medium text-slate-700 hover:text-slate-900"
                    >
                        Zu den Mitgliedsbeiträgen
                    </a>
                </div>

                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">

                    <a
                        href="{{ route('membership-fees.index', ['statusFilter' => 'open']) }}"
                        class="block rounded-xl border border-slate-200 bg-white p-5 shadow-sm transition hover:border-slate-300 hover:shadow-md"
                    >
                        <div class="text-sm text-slate-500">
                            Offen
                        </div>

                        <div class="mt-2 text-3xl font-semibold text-slate-900">
                            {{ $membershipFeeStats['open'] }}
                        </div>
                    </a>

                    <a
                        href="{{ route('membership-fees.index', ['statusFilter' => 'paid']) }}"
                        class="block rounded-xl border border-slate-200 bg-white p-5 shadow-sm transition hover:border-slate-300 hover:shadow-md"
                    >
                        <div class="text-sm text-slate-500">
                            Bezahlt
                        </div>

                        <div class="mt-2 text-3xl font-semibold text-emerald-700">
                            {{ $membershipFeeStats['paid'] }}
                        </div>
                    </a>

                    <a
                        href="{{ route('membership-fees.index', ['statusFilter' => 'exempt']) }}"
                        class="block rounded-xl border border-slate-200 bg-white p-5 shadow-sm transition hover:border-slate-300 hover:shadow-md"
                    >
                        <div class="text-sm text-slate-500">
                            Befreit
                        </div>

                        <div class="mt-2 text-3xl font-semibold text-slate-700">
                            {{ $membershipFeeStats['exempt'] }}
                        </div>
                    </a>

                    <a
                        href="{{ route('membership-fees.index', ['reminderFilter' => 'due']) }}"
                        class="block rounded-xl border border-amber-200 bg-amber-50 p-5 shadow-sm transition hover:border-amber-300 hover:shadow-md"
                    >
                        <div class="text-sm text-amber-700">
                            Erinnerung fällig
                        </div>

                        <div class="mt-2 text-3xl font-semibold text-amber-800">
                            {{ $membershipFeeStats['reminder_due'] }}
                        </div>
                    </a>

                    <a
                        href="{{ route('membership-fees.index', ['reminderFilter' => 'sent']) }}"
                        class="block rounded-xl border border-blue-200 bg-blue-50 p-5 shadow-sm transition hover:border-blue-300 hover:shadow-md"
                    >
                        <div class="text-sm text-blue-700">
                            Bereits erinnert
                        </div>

                        <div class="mt-2 text-3xl font-semibold text-blue-800">
                            {{ $membershipFeeStats['reminded'] }}
                        </div>
                    </a>

                </div>

            </div>

        @endif
    </div>

    <!-- offene und bezahlte beträge -->
    <div class="grid gap-4 mt-6 md:grid-cols-2">
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="text-sm text-slate-500">
                Offener Betrag
            </div>

            <div class="mt-2 text-3xl font-semibold text-red-700">
                {{ number_format(
                    $membershipFeeStats['open_amount'],
                    2,
                    ',',
                    '.'
                ) }} €
            </div>
        </div>

        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="text-sm text-slate-500">
                Bereits bezahlt
            </div>

            <div class="mt-2 text-3xl font-semibold text-emerald-700">
                {{ number_format(
                    $membershipFeeStats['paid_amount'],
                    2,
                    ',',
                    '.'
                ) }} €
            </div>
        </div>
    </div>

    <!-- nächste Aktionen -->
    <div>
        @php
            $membershipFeeActions = $this->membershipFeeActions();
        @endphp

        <div class="mt-6">
            <h3 class="mb-3 text-base font-semibold text-slate-900">
                Nächste Aktionen
            </h3>

            <div class="grid gap-4 md:grid-cols-2">

                <a
                    href="{{ route('membership-fees.index', ['reminderFilter' => 'due']) }}"
                    class="block rounded-xl border border-amber-200 bg-amber-50 p-5 shadow-sm transition hover:border-amber-300 hover:shadow-md"
                >
                    <div class="text-sm font-medium text-amber-700">
                        Fällige Erinnerungen
                    </div>

                    <div class="mt-2 text-3xl font-semibold text-amber-800">
                        {{ $membershipFeeActions['reminder_due'] }}
                    </div>

                    <div class="mt-2 text-sm text-amber-700">
                        Jetzt prüfen und versenden
                    </div>
                </a>

                <a
                    href="{{ route('membership-fees.index', [
                'statusFilter' => 'open',
                'emailFilter' => 'without_email',
            ]) }}"
                    class="block rounded-xl border border-slate-200 bg-white p-5 shadow-sm transition hover:border-slate-300 hover:shadow-md"
                >
                    <div class="text-sm font-medium text-slate-600">
                        Offene Beiträge ohne E-Mail
                    </div>

                    <div class="mt-2 text-3xl font-semibold text-slate-900">
                        {{ $membershipFeeActions['open_without_email'] }}
                    </div>

                    <div class="mt-2 text-sm text-slate-500">
                        Für Postversand vorbereiten
                    </div>
                </a>

            </div>
        </div>
    </div>



</div>
