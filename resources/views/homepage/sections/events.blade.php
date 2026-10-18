@php
    $homepageEvents = app(\App\Services\HomepageEvents::class);
    $sectionEvents = $homepageEvents->upcoming((int) $section->value('limit', 5));
@endphp

@if($homepageEvents->enabled())
    <div class="mx-auto max-w-[780px] [&_h3]:text-brand-deep">
        @include('homepage.sections._heading')

        @if($sectionEvents->isEmpty())
            <p class="opacity-70">Derzeit sind keine Termine geplant.</p>
        @else
            @include('homepage.partials.event-list', ['events' => $sectionEvents, 'compact' => true])

            <p class="mt-5"><a href="{{ route('homepage.events') }}" class="font-bold text-brand hover:underline">Alle Termine →</a></p>
        @endif
    </div>
@endif
