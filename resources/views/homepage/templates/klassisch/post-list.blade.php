<div class="divide-y divide-stone-200 border-y border-stone-200">
    @foreach($posts as $listedPost)
        <article class="flex flex-col gap-5 py-6 sm:flex-row">
            @if($listedPost->coverImage)
                <a href="{{ route('homepage.posts.show', $listedPost->slug) }}" class="flex-none">
                    <img src="{{ $imageUrls->postImage($listedPost->coverImage) }}" alt="" loading="lazy" class="aspect-[4/3] w-full rounded-sm object-cover sm:w-56">
                </a>
            @endif

            <div>
                <p class="text-xs font-semibold uppercase tracking-wider text-brand">
                    {{ $listedPost->published_at->locale('de')->translatedFormat('j. F Y') }}
                </p>

                <h3 class="break-words hyphens-auto mt-1 font-serif text-xl font-bold text-stone-900">
                    <a href="{{ route('homepage.posts.show', $listedPost->slug) }}" class="hover:text-brand">{{ $listedPost->title }}</a>
                </h3>

                <p class="mt-2 leading-relaxed text-stone-600">{{ $listedPost->teaser() }}</p>

                <a href="{{ route('homepage.posts.show', $listedPost->slug) }}" class="mt-2 inline-block text-sm font-semibold text-brand hover:underline">Weiterlesen →</a>
            </div>
        </article>
    @endforeach
</div>
