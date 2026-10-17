<div class="grid gap-8 md:grid-cols-2">
    @foreach(['bild', 'bild2'] as $index => $kind)
        @php
            $imageUrl = $imageUrls->sectionMedia($section, $kind);
            $captionTitle = $section->value('items.'.$index.'.title');
            $captionText = $section->value('items.'.$index.'.text');
        @endphp

        @if($imageUrl)
            <figure>
                <a href="{{ $imageUrl }}" data-lightbox="section-{{ $section->sectionID }}" data-caption="{{ trim($captionTitle.($captionTitle && $captionText ? ' – ' : '').$captionText) }}" class="block cursor-zoom-in">
                    <img src="{{ $imageUrl }}" alt="{{ $captionTitle ?? '' }}" loading="lazy" class="aspect-square w-full rounded-xl object-cover shadow-[0_1px_2px_rgba(0,0,0,.05),0_4px_16px_rgba(0,0,0,.06)]">
                </a>

                @if($captionTitle || $captionText)
                    <figcaption class="mt-3 text-[15px] text-[#5b665e]">
                        @if($captionTitle)<strong class="text-[#17201a]">{{ $captionTitle }}</strong>@endif
                        @if($captionTitle && $captionText) – @endif
                        {{ $captionText }}
                    </figcaption>
                @endif
            </figure>
        @endif
    @endforeach
</div>
