@php
    $boardRows = app(\App\Services\HomepageBoard::class)->byFunction();
@endphp

<div class="mx-auto max-w-[780px]">
    @include('homepage.sections._heading')

    @if($boardRows->isEmpty())
        <p class="text-[#5b665e]">Derzeit sind keine Vorstandsfunktionen eingetragen.</p>
    @else
        <div class="grid gap-px overflow-hidden rounded-xl bg-[#dfe6e0] shadow-[0_1px_2px_rgba(0,0,0,.05),0_4px_16px_rgba(0,0,0,.06)]">
            @foreach($boardRows as $row)
                <div class="grid gap-0.5 bg-white px-5 py-3.5 sm:grid-cols-[240px_1fr] sm:gap-3">
                    <span class="text-[#5b665e]">{{ $row['function'] }}</span>
                    <strong>{{ implode(', ', $row['names']) }}</strong>
                </div>
            @endforeach
        </div>
    @endif
</div>
