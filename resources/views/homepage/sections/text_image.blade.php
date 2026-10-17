@php
    $imageUrl = $imageUrls->sectionMedia($section, 'bild');
    $imageLeft = $section->value('image_position') === 'left';
@endphp

<div class="grid items-center gap-[clamp(28px,5vw,56px)] md:grid-cols-[1.1fr_1fr]">
    <div class="{{ $imageLeft ? 'md:order-2' : '' }}">
        @include('homepage.sections._heading')

        <div class="hp-content">{!! app(\App\Services\HtmlSanitizer::class)->sanitize($section->body_html) !!}</div>
    </div>

    @if($imageUrl)
        <div class="{{ $imageLeft ? 'md:order-1' : '' }}">
            <a href="{{ $imageUrl }}" data-lightbox="section-{{ $section->sectionID }}" data-caption="{{ $section->heading }}" class="block cursor-zoom-in">
                <img src="{{ $imageUrl }}" alt="{{ $section->heading ?? '' }}" loading="lazy" class="w-full rounded-xl shadow-[0_1px_2px_rgba(0,0,0,.05),0_4px_16px_rgba(0,0,0,.06)]">
            </a>
        </div>
    @endif
</div>
