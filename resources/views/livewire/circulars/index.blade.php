<div class="space-y-6">

    <div class="flex items-center justify-between">

        <div>
            <h2 class="text-2xl font-bold tracking-tight">
                Rundschreiben
            </h2>

            <p class="mt-1 text-sm text-slate-500">
                Rundschreiben erstellen, versenden und verwalten.
            </p>
        </div>

        <button
            type="button"
            wire:click="$set('showCreateDialog', true)"
            class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800"
        >
            Neues Rundschreiben
        </button>

    </div>


    @if(session('success'))
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
            {{ session('success') }}
        </div>
    @endif

    @if(session('warning'))
        <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
            {{ session('warning') }}
        </div>
    @endif


    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">

        <table class="min-w-full divide-y divide-slate-200">

            <thead class="bg-slate-50">
            <tr>
                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                    Titel
                </th>

                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                    Status
                </th>

                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                    Empfänger
                </th>

                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                    Offen
                </th>

                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                    Versand
                </th>

                <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">
                    Aktionen
                </th>
            </tr>
            </thead>


            <tbody class="divide-y divide-slate-100 bg-white">

            @forelse($circulars as $circular)

                @php
                    $emailRecipientsCount = $circular->recipients
                        ->where('delivery_method', 'email')
                        ->count();

                    $openTotal =
                        $circular->open_email_recipients_count
                        + $circular->open_post_recipients_count;
                @endphp

                <tr class="align-top">

                    {{-- Titel --}}
                    <td class="px-4 py-4">

                        <div class="font-medium text-slate-900">
                            {{ $circular->title }}
                        </div>

                        @if($circular->subject)
                            <div class="mt-1 max-w-xs truncate text-xs text-slate-500">
                                {{ $circular->subject }}
                            </div>
                        @endif

                    </td>


                    {{-- Status --}}
                    <td class="px-4 py-4 text-sm">

                        @if($circular->status === 'sent')

                            <span class="inline-flex rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-800">
                                Versendet
                            </span>

                        @else

                            <span class="inline-flex rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-800">
                                Entwurf
                            </span>

                        @endif

                    </td>


                    {{-- Empfänger --}}
                    <td class="px-4 py-4 text-sm text-slate-700">

                        <div class="font-medium">
                            {{ $circular->recipients_count }}
                            gesamt
                        </div>

                        <div class="mt-1 text-xs text-slate-500">
                            E-Mail:
                            {{ $emailRecipientsCount }}

                            · Post:
                            {{ $circular->post_recipients_count }}
                        </div>

                    </td>


                    {{-- Offen --}}
                    <td class="px-4 py-4 text-sm">

                        @if($openTotal > 0)

                            <div class="font-medium text-amber-700">
                                {{ $openTotal }} offen
                            </div>

                            <div class="mt-1 text-xs text-slate-500">
                                E-Mail:
                                {{ $circular->open_email_recipients_count }}

                                · Post:
                                {{ $circular->open_post_recipients_count }}
                            </div>

                        @else

                            <span class="inline-flex rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-700">
                                Alles erledigt
                            </span>

                        @endif

                    </td>


                    {{-- Versanddatum --}}
                    <td class="px-4 py-4 text-sm text-slate-500">

                        @if($circular->sent_at)

                            <div>
                                {{ $circular->sent_at->format('d.m.Y') }}
                            </div>

                            <div class="text-xs text-slate-400">
                                {{ $circular->sent_at->format('H:i') }} Uhr
                            </div>

                        @else

                            <div>
                                Erstellt:
                                {{ $circular->created_at?->format('d.m.Y') }}
                            </div>

                            <div class="text-xs text-slate-400">
                                {{ $circular->created_at?->format('H:i') }} Uhr
                            </div>

                        @endif

                    </td>


                    {{-- Aktionen --}}
                    <td class="px-4 py-4">

                        <div class="flex flex-wrap justify-end gap-2">

                            {{-- Bearbeiten --}}
                            <button
                                type="button"
                                wire:click="editCircular({{ $circular->circularID }})"
                                class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                            >
                                Bearbeiten
                            </button>


                            {{-- E-Mail --}}
                            @if($emailRecipientsCount > 0)

                                @if($circular->open_email_recipients_count > 0)

                                    <form
                                        method="POST"
                                        action="{{ route('circulars.send-emails', $circular) }}"
                                        class="inline"
                                        onsubmit="return confirm('Rundschreiben jetzt an alle noch offenen E-Mail-Empfänger senden?')"
                                    >
                                        @csrf

                                        <button
                                            type="submit"
                                            class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs font-semibold text-emerald-800 hover:bg-emerald-100"
                                        >
                                            E-Mail versenden
                                        </button>

                                    </form>

                                @else

                                    <span class="inline-flex items-center rounded-lg bg-emerald-50 px-3 py-2 text-xs font-semibold text-emerald-700">
                                        E-Mail versendet
                                    </span>

                                @endif

                            @endif


                            {{-- Post-PDF --}}
                            @if($circular->post_recipients_count > 0)

                                <a
                                    href="{{ route('circulars.post-pdf', $circular) }}"
                                    target="_blank"
                                    class="rounded-lg border border-blue-200 bg-blue-50 px-3 py-2 text-xs font-semibold text-blue-800 hover:bg-blue-100"
                                >
                                    Post-PDF
                                </a>

                            @endif


                            {{-- Post als versendet markieren --}}
                            @if($circular->post_recipients_count > 0)

                                @if($circular->open_post_recipients_count > 0)

                                    <form
                                        method="POST"
                                        action="{{ route('circulars.mark-post-sent', $circular) }}"
                                        class="inline"
                                        onsubmit="return confirm('Alle noch offenen Post-Empfänger als versendet markieren?')"
                                    >
                                        @csrf

                                        <button
                                            type="submit"
                                            class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs font-semibold text-amber-800 hover:bg-amber-100"
                                        >
                                            Post erledigt
                                        </button>

                                    </form>

                                @else

                                    <span class="inline-flex items-center rounded-lg bg-amber-50 px-3 py-2 text-xs font-semibold text-amber-700">
                                        Post versendet
                                    </span>

                                @endif

                            @endif

                        </div>

                    </td>

                </tr>

            @empty

                <tr>
                    <td
                        colspan="6"
                        class="px-4 py-10 text-center text-sm text-slate-500"
                    >
                        Noch keine Rundschreiben vorhanden.
                    </td>
                </tr>

            @endforelse

            </tbody>

        </table>

    </div>


    @if($showCreateDialog)

        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">

            <div class="flex max-h-[90vh] w-full max-w-4xl flex-col overflow-hidden rounded-xl bg-white shadow-xl">

                {{-- Modal-Kopf --}}
                <div class="flex items-center justify-between border-b border-slate-200 px-6 py-4">

                    <div>

                        <h3 class="text-lg font-semibold text-slate-900">
                            {{ $editingCircularID ? 'Rundschreiben bearbeiten' : 'Neues Rundschreiben' }}
                        </h3>

                        <p class="mt-1 text-xs text-slate-500">
                            Inhalt, Empfänger und Anhänge verwalten
                        </p>

                    </div>

                    <button
                        type="button"
                        wire:click="$set('showCreateDialog', false)"
                        class="rounded-lg px-3 py-2 text-sm text-slate-500 hover:bg-slate-100"
                    >
                        Schließen
                    </button>

                </div>


                {{-- Modal-Inhalt --}}
                <div class="flex-1 space-y-6 overflow-y-auto p-6">

                    {{-- Titel --}}
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">
                            Titel
                        </label>

                        <input
                            type="text"
                            wire:model="title"
                            class="w-full rounded-lg border border-slate-300 px-3 py-2"
                        >

                        @error('title')
                        <p class="mt-1 text-xs text-red-600">
                            {{ $message }}
                        </p>
                        @enderror
                    </div>


                    {{-- Template --}}
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">
                            Template
                        </label>

                        <select
                            wire:model.live="templateID"
                            class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2"
                        >
                            <option value="">
                                Kein Template
                            </option>

                            @foreach($templates as $template)

                                <option value="{{ $template->templateID }}">
                                    {{ $template->name }}
                                </option>

                            @endforeach

                        </select>
                    </div>


                    {{-- Betreff --}}
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">
                            Betreff
                        </label>

                        <input
                            type="text"
                            wire:model="subject"
                            class="w-full rounded-lg border border-slate-300 px-3 py-2"
                        >

                        @error('subject')
                        <p class="mt-1 text-xs text-red-600">
                            {{ $message }}
                        </p>
                        @enderror
                    </div>


                    {{-- Quill --}}
                    <div>

                        <label class="mb-1 block text-sm font-medium text-slate-700">
                            Text
                        </label>

                        <div
                            wire:key="circular-editor-{{ $templateID ?? 'none' }}-{{ $editingCircularID ?? 'create' }}"
                            wire:ignore
                            x-data="{ quill: null }"
                            x-init="
                                quill = new Quill($refs.editor, {
                                    theme: 'snow',
                                    modules: {
                                        toolbar: [
                                            ['bold', 'italic', 'underline'],
                                            [{ size: ['small', false, 'large', 'huge'] }],
                                            [{ color: [] }],
                                            [{ align: [] }],
                                            [{ list: 'ordered' }, { list: 'bullet' }],
                                            ['clean']
                                        ]
                                    }
                                });

                                quill.root.innerHTML = @js($bodyHtml) || '';

                                quill.on('text-change', () => {
                                    $wire.set('bodyHtml', quill.root.innerHTML, false);
                                });
                            "
                            class="space-y-3"
                        >
                            <div
                                x-ref="editor"
                                class="min-h-[280px] bg-white"
                            ></div>
                        </div>

                        @error('bodyHtml')
                        <p class="mt-1 text-xs text-red-600">
                            {{ $message }}
                        </p>
                        @enderror

                    </div>


                    {{-- Empfänger --}}
                    <div class="space-y-4 rounded-xl border border-slate-200 p-4">

                        <div>

                            <div class="mb-3">
                                <div class="font-medium text-slate-900">
                                    Empfänger
                                </div>

                                <div class="mt-1 text-xs text-slate-500">
                                    Einzelne Mitglieder oder komplette Empfängergruppen auswählen.
                                </div>
                            </div>


                            <div class="grid gap-3 md:grid-cols-3">

                                <input
                                    type="text"
                                    wire:model.live.debounce.300ms="recipientSearch"
                                    placeholder="Mitglied suchen..."
                                    class="rounded-lg border border-slate-300 px-3 py-2 text-sm"
                                >


                                <button
                                    type="button"
                                    wire:click="$set('recipientEmailFilter', 'all')"
                                    class="rounded-lg px-3 py-2 text-sm font-medium
                                        {{ $recipientEmailFilter === 'all'
                                            ? 'bg-slate-900 text-white'
                                            : 'border border-slate-300 bg-white text-slate-700'
                                        }}"
                                >
                                    Alle
                                </button>


                                <div class="flex gap-2">

                                    <button
                                        type="button"
                                        wire:click="$set('recipientEmailFilter', 'with_email')"
                                        class="flex-1 rounded-lg px-3 py-2 text-sm font-medium
                                            {{ $recipientEmailFilter === 'with_email'
                                                ? 'bg-emerald-600 text-white'
                                                : 'border border-emerald-300 bg-emerald-50 text-emerald-800'
                                            }}"
                                    >
                                        Mit E-Mail
                                    </button>


                                    <button
                                        type="button"
                                        wire:click="$set('recipientEmailFilter', 'without_email')"
                                        class="flex-1 rounded-lg px-3 py-2 text-sm font-medium
                                            {{ $recipientEmailFilter === 'without_email'
                                                ? 'bg-amber-600 text-white'
                                                : 'border border-amber-300 bg-amber-50 text-amber-800'
                                            }}"
                                    >
                                        Ohne E-Mail
                                    </button>

                                </div>

                            </div>

                        </div>


                        {{-- Auswahlaktionen --}}
                        <div class="flex flex-wrap items-center gap-2">

                            <button
                                type="button"
                                wire:click="selectAllFilteredRecipients"
                                class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
                            >
                                Alle gefilterten auswählen
                            </button>

                            <button
                                type="button"
                                wire:click="clearRecipientSelection"
                                class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
                            >
                                Auswahl aufheben
                            </button>

                            <span class="ml-auto rounded-full bg-slate-100 px-3 py-1 text-xs font-medium text-slate-600">
                                {{ count($selectedRecipients) }} ausgewählt
                            </span>

                        </div>


                        {{-- Gruppen --}}
                        @if($recipientGroups->isNotEmpty())

                            <div>

                                <div class="mb-2 text-sm font-medium text-slate-700">
                                    Empfängergruppen
                                </div>

                                <div class="flex flex-wrap gap-2">

                                    @foreach($recipientGroups as $group)

                                        <button
                                            type="button"
                                            wire:click="addRecipientGroup({{ $group->groupID }})"
                                            class="rounded-lg border border-blue-200 bg-blue-50 px-3 py-2 text-sm font-medium text-blue-800 hover:bg-blue-100"
                                        >
                                            {{ $group->name }}

                                            <span class="ml-1 text-xs text-blue-600">
                                                ({{ $group->members_count }})
                                            </span>
                                        </button>

                                    @endforeach

                                </div>

                            </div>

                        @endif


                        {{-- Mitglieder --}}
                        <div class="space-y-2">

                            <div class="flex items-center justify-between">

                                <label class="text-sm font-medium text-slate-700">
                                    Mitglieder
                                </label>

                                <span class="text-xs text-slate-500">
            {{ count($selectedRecipients) }} ausgewählt
        </span>

                            </div>

                            <div class="max-h-72 overflow-y-auto rounded-lg border border-slate-200">

                                <table class="min-w-full divide-y divide-slate-200">

                                    <thead class="sticky top-0 z-10 bg-slate-50">

                                    <tr>

                                        <th class="w-12 px-4 py-2"></th>

                                        <th class="px-4 py-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                                            Mitglied
                                        </th>

                                        <th class="px-4 py-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                                            E-Mail
                                        </th>

                                    </tr>

                                    </thead>

                                    <tbody class="divide-y divide-slate-100 bg-white">

                                    @forelse($members as $member)

                                        <tr class="hover:bg-slate-50">

                                            <td class="px-4 py-2">

                                                <input
                                                    type="checkbox"
                                                    wire:model.live="selectedRecipients"
                                                    value="{{ $member->memberID }}"
                                                    class="h-4 w-4 rounded border-slate-300"
                                                >

                                            </td>

                                            <td class="px-4 py-2 text-sm text-slate-800">
                                                {{ $member->surname }}
                                                {{ $member->name }}
                                            </td>

                                            <td class="px-4 py-2 text-sm text-slate-600">
                                                {{ $member->emails->first()?->email ?? '—' }}
                                            </td>

                                        </tr>

                                    @empty

                                        <tr>

                                            <td
                                                colspan="3"
                                                class="px-4 py-6 text-center text-sm text-slate-500"
                                            >
                                                Keine Mitglieder gefunden.
                                            </td>

                                        </tr>

                                    @endforelse

                                    </tbody>

                                </table>

                            </div>

                        </div>


                        {{-- Externe Kontakte --}}
                        <div class="mt-5 space-y-2">

                            <div class="flex items-center justify-between">

                                <label class="text-sm font-medium text-slate-700">
                                    Externe Kontakte
                                </label>

                                <span class="text-xs text-slate-500">
            {{ count($selectedExternalRecipients) }} ausgewählt
        </span>

                            </div>

                            <div class="max-h-60 overflow-y-auto rounded-lg border border-slate-200">

                                <table class="min-w-full divide-y divide-slate-200">

                                    <thead class="sticky top-0 z-10 bg-slate-50">

                                    <tr>

                                        <th class="w-12 px-4 py-2"></th>

                                        <th class="px-4 py-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                                            Kontakt
                                        </th>

                                        <th class="px-4 py-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                                            Organisation
                                        </th>

                                        <th class="px-4 py-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                                            E-Mail
                                        </th>

                                    </tr>

                                    </thead>


                                    <tbody class="divide-y divide-slate-100 bg-white">

                                    @forelse($externalContacts as $contact)

                                        <tr class="hover:bg-slate-50">

                                            <td class="px-4 py-2">

                                                <input
                                                    type="checkbox"
                                                    wire:model.live="selectedExternalRecipients"
                                                    value="{{ $contact->externalContactID }}"
                                                    class="h-4 w-4 rounded border-slate-300"
                                                >

                                            </td>


                                            <td class="px-4 py-2 text-sm text-slate-800">

                                                {{ $contact->surname }}
                                                {{ $contact->name }}

                                            </td>


                                            <td class="px-4 py-2 text-sm text-slate-600">

                                                {{ $contact->organization ?: '—' }}

                                            </td>


                                            <td class="px-4 py-2 text-sm text-slate-600">

                                                {{ $contact->email }}

                                            </td>

                                        </tr>

                                    @empty

                                        <tr>

                                            <td
                                                colspan="4"
                                                class="px-4 py-6 text-center text-sm text-slate-500"
                                            >
                                                Keine externen Kontakte vorhanden.
                                            </td>

                                        </tr>

                                    @endforelse

                                    </tbody>

                                </table>

                            </div>

                        </div>

                    </div>


                    {{-- Neue Anhänge --}}
                    <div class="rounded-xl border border-slate-200 p-4">

                        <div class="mb-3">

                            <div class="font-medium text-slate-900">
                                Anhänge
                            </div>

                            <div class="mt-1 text-xs text-slate-500">
                                PDF, Word, Excel oder Bilddateien bis 10 MB.
                            </div>

                        </div>


                        <input
                            type="file"
                            wire:model="attachments"
                            multiple
                            class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm"
                        >


                        @error('attachments.*')

                        <p class="mt-1 text-xs text-red-600">
                            {{ $message }}
                        </p>

                        @enderror


                        @if(count($attachments) > 0)

                            <div class="mt-3 space-y-2">

                                @foreach($attachments as $index => $file)

                                    <div class="flex items-center justify-between rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">

                                        <div class="min-w-0">

                                            <div class="truncate text-sm font-medium text-slate-700">
                                                {{ $file->getClientOriginalName() }}
                                            </div>

                                            <div class="text-xs text-slate-500">
                                                {{ number_format($file->getSize() / 1024 / 1024, 2, ',', '.') }}
                                                MB
                                            </div>

                                        </div>


                                        <button
                                            type="button"
                                            wire:click="removeAttachment({{ $index }})"
                                            class="ml-3 rounded-md px-2 py-1 text-xs font-medium text-red-600 hover:bg-red-50"
                                        >
                                            Entfernen
                                        </button>

                                    </div>

                                @endforeach

                            </div>

                        @endif

                    </div>


                    {{-- Bestehende Anhänge --}}
                    @if(count($existingAttachments) > 0)

                        <div class="rounded-xl border border-slate-200 p-4">

                            <div class="mb-3 font-medium text-slate-900">
                                Bereits gespeicherte Anhänge
                            </div>


                            <div class="space-y-2">

                                @foreach($existingAttachments as $attachment)

                                    <div class="flex items-center justify-between rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">

                                        <div>

                                            <div class="text-sm font-medium text-slate-700">
                                                {{ $attachment['file_name'] }}
                                            </div>


                                            @if(!empty($attachment['file_size']))

                                                <div class="text-xs text-slate-500">
                                                    {{ number_format($attachment['file_size'] / 1024 / 1024, 2, ',', '.') }}
                                                    MB
                                                </div>

                                            @endif

                                        </div>


                                        <button
                                            type="button"
                                            wire:click="removeExistingAttachment({{ $attachment['attachmentID'] }})"
                                            wire:confirm="Diesen Anhang wirklich löschen?"
                                            class="ml-3 rounded-md px-2 py-1 text-xs font-medium text-red-600 hover:bg-red-50"
                                        >
                                            Entfernen
                                        </button>

                                    </div>

                                @endforeach

                            </div>

                        </div>

                    @endif


                    {{-- Versandübersicht beim Bearbeiten --}}
                    @if($editingCircularID)

                        @php
                            $editingCircular = $circulars->firstWhere(
                                'circularID',
                                $editingCircularID
                            );

                            $emailCount = $editingCircular?->recipients
                                ?->where('delivery_method', 'email')
                                ->count() ?? 0;

                            $postCount = $editingCircular?->recipients
                                ?->where('delivery_method', 'post')
                                ->count() ?? 0;
                        @endphp


                        <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">

                            <div class="text-sm font-medium text-slate-700">
                                Versandübersicht
                            </div>

                            <div class="mt-2 flex flex-wrap gap-4 text-sm">

                                <span class="text-emerald-700">
                                    E-Mail: {{ $emailCount }}
                                </span>

                                <span class="text-amber-700">
                                    Post: {{ $postCount }}
                                </span>

                            </div>

                        </div>

                    @endif

                </div>


                {{-- Modal-Footer --}}
                <div class="flex items-center justify-between border-t border-slate-200 bg-slate-50 px-6 py-4">

                    <div class="text-xs text-slate-500">

                        @if($editingCircularID)
                            Bestehendes Rundschreiben bearbeiten
                        @else
                            Neues Rundschreiben als Entwurf speichern
                        @endif

                    </div>


                    <div class="flex gap-2">

                        <button
                            type="button"
                            wire:click="$set('showCreateDialog', false)"
                            class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
                        >
                            Abbrechen
                        </button>


                        <button
                            type="button"
                            wire:click="createCircular"
                            wire:loading.attr="disabled"
                            wire:target="createCircular"
                            class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800 disabled:opacity-50"
                        >
                            <span wire:loading.remove wire:target="createCircular">
                                {{ $editingCircularID ? 'Änderungen speichern' : 'Entwurf anlegen' }}
                            </span>

                            <span wire:loading wire:target="createCircular">
                                Speichern...
                            </span>
                        </button>

                    </div>

                </div>

            </div>

        </div>

    @endif

</div>
