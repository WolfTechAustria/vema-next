{{-- Einzelnes Bild mit Bildunterschrift; Klick öffnet die Vollbild-Ansicht. --}}
@php
    $imageUrl = $imageUrls->sectionMedia($section, 'bild');
    $caption = $section->value('caption');

    $widthClass = ($inColumn ?? false) ? '' : ([
        'narrow' => 'mx-auto max-w-[520px]',
        'wide' => '',
    ][$section->value('width', 'normal')] ?? 'mx-auto max-w-[780px]');
@endphp

@if($imageUrl)
    <figure class="{{ $widthClass }}">
        @include('homepage.sections._heading')

        <a href="{{ $imageUrl }}" data-lightbox="section-{{ $section->sectionID }}" data-caption="{{ $caption }}" class="block cursor-zoom-in">
            <img
                src="{{ $imageUrl }}"
                alt="{{ $caption ?: ($section->heading ?? '') }}"
                loading="lazy"
                class="w-full rounded-xl shadow-[0_1px_2px_rgba(0,0,0,.05),0_4px_16px_rgba(0,0,0,.06)]"
            >
        </a>

        @if($caption)
            <figcaption class="mt-3 text-center text-[15px] opacity-75">{{ $caption }}</figcaption>
        @endif
    </figure>
@endif
