{{--
    Rahmen eines Abschnitts. Vorlagen mit voller Breite ($fullBleed) setzen
    Hintergrund und Abstände wie ganze Seitenbereiche; die übrigen reihen
    Abschnitte einfach im Inhaltsbereich untereinander. Der Abstand ist je
    Abschnitt einstellbar (Kein / Klein / Normal / Groß).
--}}
@php
    $partial = 'homepage.sections.'.$section->type->value;
    $spacing = $section->value('spacing', 'normal');

    $paddingClass = [
        'none' => 'py-0',
        'small' => 'py-6 sm:py-8',
        'large' => 'py-[clamp(72px,12vw,128px)]',
    ][$spacing] ?? 'py-[clamp(48px,8vw,80px)]';

    $marginClass = [
        'none' => 'mt-0',
        'small' => 'mt-6',
        'large' => 'mt-24',
    ][$spacing] ?? 'mt-14';
@endphp

@if($fullBleed ?? false)
    <section class="{{ $paddingClass }} {{ $section->alt_background ? 'bg-[#f3f6f3]' : '' }}">
        <div class="mx-auto max-w-[1160px] px-4">
            @include($partial)
        </div>
    </section>
@else
    <section class="{{ $marginClass }} {{ $section->alt_background ? 'rounded-2xl bg-black/[.03] p-6 sm:p-10' : '' }}">
        @include($partial)
    </section>
@endif
