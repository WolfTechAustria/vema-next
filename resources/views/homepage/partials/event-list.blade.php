{{--
    Liste öffentlicher Vereinstermine mit Datum-Kachel.
    $events: Collection<ClubEvent>, $compact: ohne Beschreibung (Startseite).
--}}
<ul class="space-y-4">
    @foreach($events as $event)
        @php
            $start = $event->starts_at->locale('de');
            $end = $event->ends_at?->locale('de');
            $multiDay = $end && ! $end->isSameDay($start);

            if ($event->all_day) {
                $time = $multiDay ? 'bis '.$end->translatedFormat('D, j. F') : 'ganztägig';
            } elseif ($multiDay) {
                $time = $start->format('H:i').' Uhr bis '.$end->translatedFormat('D, j. F, H:i').' Uhr';
            } else {
                $time = $start->format('H:i').($end ? ' – '.$end->format('H:i') : '').' Uhr';
            }
        @endphp

        <li class="flex gap-4 rounded-xl border border-black/10 bg-white p-4 shadow-[0_1px_2px_rgba(0,0,0,.04)] sm:p-5">
            <div class="flex w-16 flex-none flex-col items-center justify-center rounded-lg bg-brand py-2 text-white" aria-hidden="true">
                <span class="text-2xl font-bold leading-none">{{ $start->format('j') }}</span>
                <span class="mt-1 text-xs font-semibold uppercase tracking-wide">{{ $start->translatedFormat('M') }}</span>
            </div>

            <div class="min-w-0">
                <h3 class="break-words hyphens-auto text-lg font-bold leading-snug">{{ $event->title }}</h3>

                <p class="mt-1 text-sm opacity-75">
                    <time datetime="{{ $event->starts_at->toIso8601String() }}">{{ $start->translatedFormat('l, j. F Y') }}</time>
                    · {{ $time }}
                    @if($event->location)
                        · {{ $event->location }}
                    @endif
                </p>

                @if(! ($compact ?? false) && $event->description)
                    <p class="mt-2 whitespace-pre-line text-[15px] opacity-90">{{ $event->description }}</p>
                @endif
            </div>
        </li>
    @endforeach
</ul>
