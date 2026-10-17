@extends($templatePath.'.layout')

@php
    $coverImage = $post->images->first();
    $galleryImages = $post->images->slice(1);
@endphp

@section('content')
    <section class="py-[clamp(48px,8vw,80px)]">
        <div class="mx-auto max-w-[780px] px-4">
            <article>
                <a href="{{ route('homepage.posts.index') }}" class="mb-5 block font-semibold text-brand hover:text-brand-dark">← Alle Beiträge</a>

                @if($post->published_at)
                    <time datetime="{{ $post->published_at->toDateString() }}" class="text-sm font-bold tracking-[.03em] text-accent">
                        {{ $post->published_at->locale('de')->translatedFormat('j. F Y') }}
                    </time>
                @endif

                <h1 class="mb-6 mt-1.5 break-words hyphens-auto text-[clamp(1.8rem,5vw,2.6rem)] font-bold leading-[1.2] tracking-[-.01em] text-brand-deep">{{ $post->title }}</h1>

                @if($coverImage)
                    <a href="{{ $imageUrls->postImage($coverImage, 'gross') }}" target="_blank" rel="noopener">
                        <img src="{{ $imageUrls->postImage($coverImage, 'gross') }}" alt="{{ $coverImage->caption ?? $post->title }}" class="mb-7 max-h-[560px] w-full rounded-xl object-cover">
                    </a>
                @endif

                @if($post->excerpt)
                    <p class="mb-5 text-[1.25rem] font-semibold text-brand-deep">{{ $post->excerpt }}</p>
                @endif

                @if(filled($bodyHtml))
                    <div class="hp-content">{!! $bodyHtml !!}</div>
                @endif

                @include('homepage.partials.downloads', ['attachments' => $post->attachments])
            </article>
        </div>

        @if($galleryImages->isNotEmpty())
            <div class="mx-auto max-w-[1160px] px-4">
                <h2 class="mb-5 mt-12 text-[clamp(1.4rem,3vw,1.9rem)] font-bold text-brand-deep">
                    Bildergalerie <small class="text-[.7em] font-normal text-[#5b665e]">({{ $galleryImages->count() }} Fotos)</small>
                </h2>

                <div class="grid grid-cols-2 gap-2.5 sm:grid-cols-[repeat(auto-fill,minmax(180px,1fr))]">
                    @foreach($galleryImages as $image)
                        <a href="{{ $imageUrls->postImage($image, 'gross') }}" target="_blank" rel="noopener" title="{{ $image->caption ?? '' }}">
                            <img src="{{ $imageUrls->postImage($image) }}" alt="{{ $image->caption ?? '' }}" loading="lazy" class="aspect-square w-full rounded-lg object-cover transition hover:opacity-85">
                        </a>
                    @endforeach
                </div>
            </div>
        @endif
    </section>
@endsection
