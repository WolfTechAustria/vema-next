<div class="space-y-6">

    @include('partials.flash-messages')

    @include('partials.federation-nav')

    <div>
        <h2 class="text-2xl font-bold tracking-tight">
            Mitteilungen
        </h2>

        <p class="mt-1 text-sm text-slate-500">
            Mitteilungen erscheinen bei allen Mitgliedsvereinen unter „Verband“. Zusätzlich geht ein Hinweis per E-Mail
            an die Kontaktadresse jedes Vereins (zählt zu eurem E-Mail-Kontingent).
        </p>
    </div>

    @if($isAdmin)
        <form wire:submit="send" class="max-w-2xl space-y-4 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Betreff</label>
                <input type="text" wire:model="subject" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                @error('subject') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Text</label>
                <textarea wire:model="body" rows="8" class="w-full rounded-lg border border-slate-300 px-3 py-2"></textarea>
                @error('body') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Anhänge</label>
                <x-upload-guard :max-kb="10240">
                    <input type="file" multiple wire:model="attachments" class="block w-full text-sm">
                </x-upload-guard>
                <x-upload-error field="attachments" :max-kb="10240" />

                @if($attachments)
                    <ul class="mt-2 space-y-1 text-sm">
                        @foreach($attachments as $index => $attachment)
                            <li wire:key="attachment-{{ $index }}" class="flex items-center justify-between gap-2">
                                <span>{{ $attachment->getClientOriginalName() }}</span>
                                <button type="button" wire:click="removeAttachment({{ $index }})" class="text-xs text-red-600 hover:underline">Entfernen</button>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>

            <button
                type="submit"
                wire:confirm="Mitteilung an alle Mitgliedsvereine senden?"
                class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white"
            >
                An alle Mitgliedsvereine senden
            </button>
        </form>
    @endif

    <section class="space-y-3">
        <h3 class="font-semibold text-slate-900">Gesendet</h3>

        @forelse($messages as $message)
            <article wire:key="message-{{ $message->id }}" class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <div>
                        <h4 class="font-semibold text-slate-900">{{ $message->subject }}</h4>
                        <p class="text-xs text-slate-500">
                            {{ $message->created_at->format('d.m.Y H:i') }} · {{ $message->sent_by }} ·
                            gelesen von {{ $message->readCount() }} / {{ $message->recipients_count }} Vereinen
                        </p>
                    </div>

                    @if($isAdmin)
                        <button type="button" wire:click="deleteMessage({{ $message->id }})" wire:confirm="Mitteilung bei allen Vereinen löschen?" class="text-xs text-red-600 hover:underline">
                            Löschen
                        </button>
                    @endif
                </div>

                <div class="mt-3 text-sm text-slate-700">{!! nl2br(e($message->body)) !!}</div>

                @if($message->attachments->isNotEmpty())
                    <ul class="mt-3 flex flex-wrap gap-2 text-sm">
                        @foreach($message->attachments as $attachment)
                            <li><a href="{{ route('federation-files.show', $attachment) }}" target="_blank" class="text-slate-700 underline">{{ $attachment->file_name }}</a></li>
                        @endforeach
                    </ul>
                @endif
            </article>
        @empty
            <p class="text-sm text-slate-500">Noch keine Mitteilungen gesendet.</p>
        @endforelse
    </section>

</div>
