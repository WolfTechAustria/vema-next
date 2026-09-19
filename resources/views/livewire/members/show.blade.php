<div class="space-y-6">

    @include('partials.flash-messages')

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

    <!-- Dienste -->
    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">

        <div class="flex items-center justify-between border-b border-slate-200 px-6 py-4">
            <div>
                <h3 class="font-semibold text-slate-900">
                    Dienste
                </h3>
                <p class="mt-1 text-xs text-slate-500">
                    Eingeteilte Dienste dieses Mitglieds
                </p>
            </div>

            <div class="rounded-full bg-slate-100 px-3 py-1 text-xs font-medium text-slate-600">
                {{ $member->dutyAssignments->count() }} Einträge
            </div>
        </div>

        @php
            $sortedAssignments = $member->dutyAssignments
                ->filter(fn ($a) => $a->event !== null)
                ->sortByDesc(fn ($a) => $a->event->duty_date);
        @endphp

        @forelse ($sortedAssignments as $assignment)
            <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4 last:border-b-0">
                <div>
                    <div class="font-medium text-slate-900">
                        {{ $assignment->event->duty_name }}
                    </div>
                    <div class="text-sm text-slate-500">
                        {{ $assignment->event->duty_date->format('d.m.Y') }}
                    </div>
                </div>

                @if ($assignment->event->duty_date->isFuture())
                    <span class="inline-flex rounded-full bg-blue-100 px-2.5 py-1 text-xs font-semibold text-blue-800">
                    Bevorstehend
                </span>
                @else
                    <span class="inline-flex rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600">
                    Vergangen
                </span>
                @endif
            </div>
        @empty
            <p class="px-6 py-4 text-sm text-slate-500">
                Diesem Mitglied sind aktuell keine Dienste zugeteilt.
            </p>
        @endforelse

    </div>

    <!-- Beitragsvorschreibungen -->
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

    <!-- Rundschreiben -->
    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">

        <div class="flex items-center justify-between border-b border-slate-200 px-6 py-4">

            <div>
                <h3 class="font-semibold text-slate-900">
                    Rundschreiben
                </h3>

                <p class="mt-1 text-xs text-slate-500">
                    Versandte und geplante Mitteilungen an dieses Mitglied
                </p>
            </div>

            <div class="rounded-full bg-slate-100 px-3 py-1 text-xs font-medium text-slate-600">
                {{ $member->circularRecipients->count() }}
                Einträge
            </div>

        </div>


        @forelse($member->circularRecipients as $recipient)

            @php
                $circular = $recipient->circular;
            @endphp

            <div class="border-b border-slate-100 px-6 py-4 last:border-b-0">

                <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">

                    {{-- Rundschreiben --}}
                    <div class="min-w-0 flex-1">

                        <div class="flex flex-wrap items-center gap-2">

                            <div class="truncate font-medium text-slate-900">
                                {{ $circular?->title ?? 'Rundschreiben' }}
                            </div>

                            @if($recipient->delivery_method === 'email')

                                <span class="inline-flex rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-800">
                                E-Mail
                            </span>

                            @elseif($recipient->delivery_method === 'post')

                                <span class="inline-flex rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-800">
                                Post
                            </span>

                            @endif


                            @if($recipient->sent_at)

                                <span class="inline-flex rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-600">
                                Versendet
                            </span>

                            @else

                                <span class="inline-flex rounded-full bg-red-50 px-2.5 py-1 text-xs font-medium text-red-700">
                                Offen
                            </span>

                            @endif

                        </div>


                        @if($circular?->subject)

                            <div class="mt-1 text-sm text-slate-500">
                                {{ $circular->subject }}
                            </div>

                        @endif


                        <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-slate-400">

                            @if($recipient->sent_at)

                                <span>
                                Versand:
                                {{ $recipient->sent_at->format('d.m.Y H:i') }}
                            </span>

                            @else

                                <span>
                                Noch nicht versendet
                            </span>

                            @endif


                            @if($recipient->delivery_method === 'email' && $recipient->email)

                                <span>
                                {{ $recipient->email }}
                            </span>

                            @endif


                            @if($circular?->attachments?->isNotEmpty())

                                <span>
                                📎 {{ $circular->attachments->count() }}
                                    {{ $circular->attachments->count() === 1 ? 'Anhang' : 'Anhänge' }}
                            </span>

                            @endif

                        </div>

                    </div>


                    {{-- Aktionen --}}
                    <div class="flex shrink-0 items-center gap-2">

                        @if($recipient->delivery_method === 'email')

                            <button
                                type="button"
                                wire:click="showCircularEmail({{ $recipient->recipientID }})"
                                class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs font-semibold text-emerald-800 hover:bg-emerald-100"
                            >
                                E-Mail anzeigen
                            </button>

                        @elseif($recipient->delivery_method === 'post')

                            <a
                                href="{{ route('circulars.recipient.preview', [
                                'circular' => $recipient->circularID,
                                'member' => $member->memberID,
                            ]) }}"
                                target="_blank"
                                class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs font-semibold text-amber-800 hover:bg-amber-100"
                            >
                                PDF öffnen
                            </a>

                        @endif

                    </div>

                </div>

            </div>

        @empty

            <div class="px-6 py-10 text-center">

                <div class="text-sm font-medium text-slate-600">
                    Noch keine Rundschreiben vorhanden
                </div>

                <div class="mt-1 text-xs text-slate-400">
                    Sobald dieses Mitglied ein Rundschreiben erhält, erscheint es hier.
                </div>

            </div>

        @endforelse

    </div>


    <!-- Modal Email Preview -->
    @if($showCircularEmailModal && $selectedCircularRecipientID)

        @php
            $selectedRecipient = $member->circularRecipients
                ->firstWhere('recipientID', $selectedCircularRecipientID);

            $selectedCircular = $selectedRecipient?->circular;

            $body = $selectedRecipient && $selectedCircular
                ? app(\App\Services\TemplateRendererService::class)->circular(
                    $selectedCircular->body_html ?? '',
                    $member
                )
                : '';
        @endphp

        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">

            <div class="flex max-h-[90vh] w-full max-w-4xl flex-col overflow-hidden rounded-xl bg-white shadow-xl">

                <div class="flex items-center justify-between border-b border-slate-200 px-6 py-4">

                    <div>
                        <div class="text-xs font-semibold uppercase tracking-wide text-slate-400">
                            E-Mail
                        </div>

                        <h3 class="mt-1 text-lg font-semibold text-slate-900">
                            {{ $selectedCircular?->subject ?: $selectedCircular?->title }}
                        </h3>
                    </div>

                    <button
                        type="button"
                        wire:click="$set('showCircularEmailModal', false)"
                        class="rounded-lg px-3 py-2 text-sm text-slate-500 hover:bg-slate-100"
                    >
                        Schließen
                    </button>

                </div>

                <div class="flex-1 overflow-y-auto p-6">

                    @if($selectedRecipient && $selectedCircular)

                        <div class="space-y-6">

                            <div class="rounded-lg bg-slate-50 p-4 text-sm text-slate-600">

                                <div>
                                <span class="font-medium">
                                    An:
                                </span>

                                    {{ $member->full_name }}
                                </div>

                                <div class="mt-1">
                                <span class="font-medium">
                                    E-Mail:
                                </span>

                                    {{ $selectedRecipient->email }}
                                </div>

                                @if($selectedRecipient->sent_at)

                                    <div class="mt-1">
                                    <span class="font-medium">
                                        Versandt:
                                    </span>

                                        {{ $selectedRecipient->sent_at->format('d.m.Y H:i') }}
                                    </div>

                                @endif

                            </div>

                            <div class="prose max-w-none">
                                {!! $body !!}
                            </div>

                            @if($selectedCircular->attachments->isNotEmpty())

                                <div class="border-t border-slate-200 pt-5">

                                    <div class="mb-3 text-sm font-semibold text-slate-700">
                                        Anhänge
                                    </div>

                                    <div class="space-y-2">

                                        @foreach($selectedCircular->attachments as $attachment)

                                            <div class="flex items-center justify-between rounded-lg border border-slate-200 bg-slate-50 px-4 py-3">

                                                <div>

                                                    <a
                                                        href="{{ route('circular-attachments.show', $attachment) }}"
                                                        target="_blank"
                                                        class="text-sm font-medium text-blue-600 hover:text-blue-800"
                                                    >
                                                        📎 {{ $attachment->file_name }}
                                                    </a>

                                                    @if($attachment->file_size)

                                                        <div class="mt-1 text-xs text-slate-500">
                                                            {{
                                                                number_format(
                                                                    $attachment->file_size / 1024 / 1024,
                                                                    2,
                                                                    ',',
                                                                    '.'
                                                                )
                                                            }}
                                                            MB
                                                        </div>

                                                    @endif

                                                </div>

                                            </div>

                                        @endforeach

                                    </div>

                                </div>

                            @endif

                        </div>

                    @endif

                </div>

            </div>

        </div>

    @endif

</div>
