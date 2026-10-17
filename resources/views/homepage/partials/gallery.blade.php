{{-- Bildergalerie eines Beitrags; Klick öffnet das Großbild. --}}
@if($images->count() > 1)
    <div class="mt-10 grid grid-cols-2 gap-3 sm:grid-cols-3">
        @foreach($images as $image)
            <figure>
                <a href="{{ $imageUrls->postImage($image, 'gross') }}" target="_blank" rel="noopener" class="block overflow-hidden rounded-lg">
                    <img
                        src="{{ $imageUrls->postImage($image) }}"
                        alt="{{ $image->caption ?? '' }}"
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
