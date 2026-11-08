<div class="mx-auto max-w-4xl space-y-6">

    <section class="rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-6 py-4">
            <h3 class="font-semibold text-slate-900">Aktuell bei dir</h3>
            <p class="mt-1 text-sm text-slate-500">
                Material, das dir der Verein ausgegeben hat, z. B. Tracht oder Instrument. Bei Fragen wende dich bitte an den Zeugwart.
            </p>
        </div>

        @forelse($openLoans as $loan)
            <div wire:key="open-{{ $loan->loanID }}" class="flex items-center gap-4 border-b border-slate-100 px-6 py-4 last:border-b-0">
                @if($photoUrl = $photos->url($loan->item, 'portal'))
                    <img src="{{ $photoUrl }}" alt="" class="h-14 w-14 rounded-md object-cover">
                @endif

                <div class="min-w-0">
                    <div class="font-medium text-slate-900">{{ $loan->item->label() }}</div>
                    <div class="text-sm text-slate-500">
                        @if($loan->item->category) {{ $loan->item->category->name }} · @endif
                        seit {{ $loan->issued_on->format('d.m.Y') }}
                        @if($loan->due_on)
                            · Rückgabe bis <span class="{{ $loan->isOverdue() ? 'font-semibold text-red-700' : '' }}">{{ $loan->due_on->format('d.m.Y') }}</span>
                        @endif
                    </div>
                </div>
            </div>
        @empty
            <p class="px-6 py-8 text-center text-sm text-slate-500">Derzeit hast du kein Material des Vereins.</p>
        @endforelse
    </section>

    @if($pastLoans->isNotEmpty())
        <section class="rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-6 py-4">
                <h3 class="font-semibold text-slate-900">Zurückgegeben</h3>
            </div>

            <ul class="divide-y divide-slate-100">
                @foreach($pastLoans as $loan)
                    <li wire:key="past-{{ $loan->loanID }}" class="flex flex-wrap justify-between gap-2 px-6 py-3 text-sm">
                        <span class="text-slate-700">{{ $loan->item->label() }}</span>
                        <span class="text-slate-500">{{ $loan->issued_on->format('d.m.Y') }} – {{ $loan->returned_on->format('d.m.Y') }}</span>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

</div>
