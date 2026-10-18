{{-- Inhalt der Seite „Termine“: kommende Termine nach Monat, vergangene einklappbar. --}}
@if($upcomingByMonth->isEmpty())
    <p class="opacity-70">Derzeit sind keine Termine geplant.</p>
@else
    <div class="space-y-10">
        @foreach($upcomingByMonth as $month => $monthEvents)
            <section>
                <h2 class="mb-4 text-xl font-bold">{{ $month }}</h2>
                @include('homepage.partials.event-list', ['events' => $monthEvents])
            </section>
        @endforeach
    </div>
@endif

@if($pastEvents->isNotEmpty())
    <details class="mt-12 rounded-xl border border-black/10 p-4 sm:p-5">
        <summary class="cursor-pointer font-semibold">Vergangene Termine ({{ $pastEvents->count() }})</summary>

        <div class="mt-4 opacity-80">
            @include('homepage.partials.event-list', ['events' => $pastEvents, 'compact' => true])
        </div>
    </details>
@endif
