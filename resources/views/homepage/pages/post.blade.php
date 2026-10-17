@extends($templatePath.'.layout')

@section('content')
    <article class="mx-auto max-w-3xl">
        <a href="{{ route('homepage.posts.index') }}" class="text-sm font-semibold text-brand hover:underline">← Alle News</a>

        @if($post->published_at)
            <p class="mt-6 text-sm font-semibold uppercase tracking-wide opacity-60">
                {{ $post->published_at->locale('de')->translatedFormat('j. F Y') }}
            </p>
        @endif

        @include($templatePath.'.section-heading', ['heading' => $post->title, 'asPageTitle' => true])

        @if($post->images->isNotEmpty())
            <figure class="mb-8">
                <img
                    src="{{ $imageUrls->postImage($post->images->first(), 'gross') }}"
                    alt="{{ $post->images->first()->caption ?? '' }}"
                    class="w-full rounded-xl object-cover"
                >

                @if($post->images->first()->caption)
                    <figcaption class="mt-2 text-sm opacity-70">{{ $post->images->first()->caption }}</figcaption>
                @endif
            </figure>
        @endif

        @if($post->excerpt)
            <p class="mb-6 text-xl font-medium leading-relaxed">{{ $post->excerpt }}</p>
        @endif

        <div class="hp-content">{!! $bodyHtml !!}</div>

        @include('homepage.partials.downloads', ['attachments' => $post->attachments])

        @include('homepage.partials.gallery', ['images' => $post->images])
    </article>
@endsection
