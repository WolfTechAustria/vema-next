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

                <th class="px-4 py-3"></th>
            </tr>
            </thead>

            <tbody class="divide-y divide-slate-100">

            @forelse($circulars as $circular)

                <tr>

                    <td class="px-4 py-3 text-sm font-medium text-slate-900">
                        {{ $circular->title }}
                    </td>

                    <td class="px-4 py-3 text-sm">

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

                    <td class="px-4 py-3 text-sm text-slate-700">

                        <div>
                            Gesamt: {{ $circular->recipients_count }}
                        </div>

                        <div class="mt-1 text-xs text-slate-500">
                            E-Mail:
                            {{ $circular->recipients->where('delivery_method', 'email')->count() }}

                            · Post:
                            {{ $circular->post_recipients_count }}
                        </div>

                    </td>

                    <td class="px-4 py-3 text-sm text-slate-700">

                        @php
                            $openTotal =
                                $circular->open_email_recipients_count
                                + $circular->open_post_recipients_count;
                        @endphp

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

                            <span class="text-emerald-700">
            Alles erledigt
        </span>

                        @endif

                    </td>

                    <td class="px-4 py-3 text-sm text-slate-500">

                        @if($circular->sent_at)

                            {{ $circular->sent_at->format('d.m.Y H:i') }}

                        @else

                            Erstellt:
                            {{ $circular->created_at?->format('d.m.Y H:i') }}

                        @endif

                    </td>

                </tr>

            @empty

                <tr>
                    <td
                        colspan="4"
                        class="px-4 py-8 text-center text-sm text-slate-500"
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

                <div class="border-b border-slate-200 px-6 py-4">
                    <h3 class="text-lg font-semibold text-slate-900">
                        Neues Rundschreiben
                    </h3>
                </div>

                <div class="flex-1 space-y-5 overflow-y-auto p-6">




                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">
                            Titel
                        </label>

                        <input
                            type="text"
                            wire:model="title"
                            class="w-full rounded-lg border border-slate-300 px-3 py-2"
                        >
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">
                            Template
                        </label>

                        <select
                            wire:model.live ="templateID"
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

                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">
                            Betreff
                        </label>

                        <input
                            type="text"
                            wire:model="subject"
                            class="w-full rounded-lg border border-slate-300 px-3 py-2"
                        >
                    </div>

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
                    </div>

                    <div class="space-y-4">

                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-700">
                                Empfänger
                            </label>

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

                        <div class="flex items-center gap-2">

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

                            <span class="ml-2 text-sm text-slate-500">
            {{ count($selectedRecipients) }} ausgewählt
        </span>

                        </div>

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

                        <div class="max-h-72 overflow-y-auto rounded-lg border border-slate-200">

                            <table class="min-w-full divide-y divide-slate-200">

                                <thead class="sticky top-0 bg-slate-50">
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

                                    <tr>

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

                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">
                            Anhänge
                        </label>

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
                                                {{ number_format($file->getSize() / 1024 / 1024, 2, ',', '.') }} MB
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

                    @if(count($existingAttachments) > 0)

                        <div class="mt-4 space-y-2">

                            <div class="text-sm font-medium text-slate-700">
                                Bereits gespeicherte Anhänge
                            </div>

                            @foreach($existingAttachments as $attachment)

                                <div class="flex items-center justify-between rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">

                                    <div>
                                        <div class="text-sm font-medium text-slate-700">
                                            {{ $attachment['file_name'] }}
                                        </div>

                                        @if(!empty($attachment['file_size']))
                                            <div class="text-xs text-slate-500">
                                                {{ number_format($attachment['file_size'] / 1024 / 1024, 2, ',', '.') }} MB
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

                    @endif

                    @if($editingCircularID)

                        @php
                            $editingCircular = $circulars->firstWhere('circularID', $editingCircularID);

                            $emailCount = $editingCircular?->recipients
                                ?->where('delivery_method', 'email')
                                ->count() ?? 0;

                            $postCount = $editingCircular?->recipients
                                ?->where('delivery_method', 'post')
                                ->count() ?? 0;
                        @endphp

                        <div class="rounded-lg border border-slate-200 bg-slate-50 p-4">

                            <div class="text-sm font-medium text-slate-700">
                                Versandübersicht
                            </div>

                            <div class="mt-2 flex gap-4 text-sm">

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



                <div class="flex justify-end gap-2 border-t border-slate-200 bg-slate-50 px-6 py-4">



                    <button
                        type="button"
                        wire:click="$set('showCreateDialog', false)"
                        class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700"
                    >
                        Abbrechen
                    </button>

                    <button
                        type="button"
                        wire:click="createCircular"
                        class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white"
                    >
                        Entwurf anlegen
                    </button>

                </div>



            </div>



        </div>

    @endif

</div>
