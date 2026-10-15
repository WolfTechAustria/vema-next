<div class="mx-auto max-w-4xl space-y-6">

    <section class="rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-6 py-4">
            <h3 class="font-semibold text-slate-900">
                Dokumente
            </h3>
            <p class="mt-1 text-sm text-slate-500">
                Unterlagen, die der Verein für dich hinterlegt hat, z. B. deine Beitrittserklärung.
                Beitragsschreiben und Rundschreiben findest du unter „Meine Beiträge“ und „Meine Rundschreiben“.
            </p>
        </div>

        @forelse($documents as $document)
            <div class="flex flex-col gap-3 border-b border-slate-100 px-6 py-4 last:border-b-0 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex min-w-0 items-center gap-3">
                    @include('livewire.members.partials.file-icon', [
                        'kind' => $document->isPdf() ? 'pdf' : ($document->isImage() ? 'image' : 'other'),
                        'size' => 'h-9 w-9',
                    ])

                    <div class="min-w-0">
                        <div class="truncate font-medium text-slate-900">{{ $document->name }}</div>
                        <div class="text-sm text-slate-500">
                            {{ $document->created_at->format('d.m.Y') }}
                            @if($document->category) · {{ $document->category }} @endif
                            @if($document->file_size) · {{ \Illuminate\Support\Number::fileSize($document->file_size, precision: 1) }} @endif
                        </div>
                    </div>
                </div>

                <div class="flex shrink-0 gap-2">
                    @if($document->isPdf() || $document->isImage())
                        <a
                            href="{{ route('member.documents.file', $document) }}"
                            target="_blank"
                            class="inline-flex items-center rounded-md border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
                        >
                            Ansehen
                        </a>
                    @endif

                    <a
                        href="{{ route('member.documents.file', [$document, 'download' => 1]) }}"
                        class="inline-flex items-center rounded-md bg-slate-900 px-3 py-2 text-sm font-medium text-white hover:bg-slate-700"
                    >
                        Herunterladen
                    </a>
                </div>
            </div>
        @empty
            <p class="px-6 py-4 text-sm text-slate-500">
                Für dich sind noch keine Dokumente hinterlegt.
            </p>
        @endforelse
    </section>

</div>
