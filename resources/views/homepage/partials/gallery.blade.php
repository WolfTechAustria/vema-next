{{-- Bildergalerie eines Beitrags (ohne Titelbild); Klick öffnet die Vollbild-Ansicht. Ohne Bildunterschrift: „Titel – Bild 2“ als Alternativtext. --}}
@if($images->isNotEmpty())
    <div class="mt-10 grid grid-cols-2 gap-3 sm:grid-cols-3">
        @foreach($images->values() as $imageIndex => $image)
            <figure>
                <a
                    href="{{ $imageUrls->postImage($image, 'gross') }}"
                    data-lightbox="{{ $group }}"
                    data-caption="{{ $image->caption }}"
                    class="block cursor-zoom-in overflow-hidden rounded-lg"
                >
                    <img
                        src="{{ $imageUrls->postImage($image) }}"
                        alt="{{ $image->caption ?: (isset($altText) ? $altText.' – Bild '.($imageIndex + 2) : '') }}"
                        loading="lazy"
                        class="aspect-[4/3] w-full object-cover transition duration-300 hover:scale-105"
                    >
                </a>

                @if($image->caption)
                    <figcaption class="mt-1 text-sm opacity-70">{{ $image->caption }}</figcaption>
                @endif
            </figure>
        @endforeach
    </div>
@endif
