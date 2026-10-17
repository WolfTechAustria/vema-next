{{--
    Rahmen eines Abschnitts. Vorlagen mit voller Breite ($fullBleed) setzen
    Hintergrund und Abstände wie ganze Seitenbereiche; die übrigen reihen
    Abschnitte einfach im Inhaltsbereich untereinander.
--}}
@php
    $partial = 'homepage.sections.'.$section->type->value;
@endphp

@if($fullBleed ?? false)
    <section class="py-[clamp(48px,8vw,80px)] {{ $section->alt_background ? 'bg-[#f3f6f3]' : '' }}">
        <div class="mx-auto max-w-[1160px] px-4">
            @include($partial)
        </div>
    </section>
@else
    <section class="mt-14 {{ $section->alt_background ? 'rounded-2xl bg-black/[.03] p-6 sm:p-10' : '' }}">
        @include($partial)
    </section>
@endif
