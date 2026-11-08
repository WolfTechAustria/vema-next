<div class="space-y-6">

    <div>
        <h2 class="text-2xl font-bold tracking-tight">
            Neuigkeiten
        </h2>

        <p class="mt-1 text-sm text-slate-500">
            Neue Funktionen und Hinweise zu VEMA.
        </p>
    </div>

    <div class="max-w-3xl space-y-4">
        @forelse($newsItems as $news)
            @php($isNew = $seenBefore === null || $news->published_at->gt($seenBefore))

            <article wire:key="news-{{ $news->id }}" class="rounded-xl border bg-white p-6 shadow-sm {{ $isNew ? 'border-sky-300' : 'border-slate-200' }}">
                <div class="flex flex-wrap items-center gap-2">
                    <h3 class="text-lg font-semibold text-slate-900">{{ $news->title }}</h3>

                    @if($isNew)
                        <span class="rounded-full bg-sky-100 px-2 py-0.5 text-xs font-medium text-sky-800">neu</span>
                    @endif
                </div>

                <p class="mt-0.5 text-xs text-slate-500">{{ $news->published_at->format('d.m.Y') }}</p>

                <div class="mt-3 space-y-2 text-sm text-slate-700 [&_a]:underline [&_h1]:font-semibold [&_h2]:font-semibold [&_h3]:font-semibold [&_ol]:list-decimal [&_ol]:pl-5 [&_ul]:list-disc [&_ul]:pl-5">
                    {{ $news->bodyHtml() }}
                </div>
            </article>
        @empty
            <p class="rounded-xl border border-slate-200 bg-white px-4 py-8 text-center text-sm text-slate-500">
                Noch keine Neuigkeiten.
            </p>
        @endforelse
    </div>

</div>
