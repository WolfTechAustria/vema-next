<div class="mx-auto max-w-4xl space-y-6">

    @include('partials.flash-messages')

    <section class="rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-6 py-4">
            <h3 class="font-semibold text-slate-900">
                Rundschreiben
            </h3>
            <p class="mt-1 text-sm text-slate-500">
                Alle Rundschreiben, die dir zugestellt wurden.
            </p>
        </div>

        @forelse($receipts as $receipt)
            <div class="border-b border-slate-100 px-6 py-4 last:border-b-0">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <div class="font-medium text-slate-900">
                            {{ $receipt->circular->subject ?: $receipt->circular->title }}
                        </div>
                        <div class="text-sm text-slate-500">
                            {{ $receipt->sent_at->format('d.m.Y') }}
                            · {{ $receipt->delivery_method === 'post' ? 'per Post' : 'per E-Mail' }}
                        </div>
                    </div>

                    <a
                        href="{{ route('member.circulars.pdf', $receipt->circular) }}"
                        target="_blank"
                        class="inline-flex items-center justify-center gap-2 rounded-md bg-slate-900 px-3 py-2 text-sm font-medium text-white hover:bg-slate-700"
                    >
                        PDF ansehen
                    </a>
                </div>

                @if($receipt->circular->attachments->isNotEmpty())
                    <div class="mt-3 flex flex-wrap gap-2">
                        @foreach($receipt->circular->attachments as $attachment)
                            <a
                                href="{{ route('member.circulars.attachment', $attachment) }}"
                                target="_blank"
                                class="inline-flex items-center gap-1 rounded-md border border-slate-300 px-2.5 py-1 text-xs font-medium text-slate-700 hover:bg-slate-50"
                            >
                                Anhang: {{ $attachment->file_name }}
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>
        @empty
            <p class="px-6 py-4 text-sm text-slate-500">
                Bisher wurden dir keine Rundschreiben zugestellt.
            </p>
        @endforelse
    </section>

</div>
