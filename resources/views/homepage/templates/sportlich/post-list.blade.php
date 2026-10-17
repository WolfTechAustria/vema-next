<div class="grid gap-4 md:grid-cols-2">
    @foreach($posts as $listedPost)
        <a
            href="{{ route('homepage.posts.show', $listedPost->slug) }}"
            class="group relative isolate flex min-h-72 flex-col justify-end overflow-hidden bg-zinc-900 p-6 text-white {{ $loop->first ? 'md:col-span-2 md:min-h-96' : '' }}"
        >
            @if($listedPost->coverImage)
                <img
                    src="{{ $imageUrls->postImage($listedPost->coverImage, $loop->first ? 'gross' : 'klein') }}"
                    alt=""
                    loading="lazy"
                    class="absolute inset-0 -z-10 h-full w-full object-cover transition duration-500 group-hover:scale-105"
                >
            @endif

            <span class="absolute inset-0 -z-10 bg-gradient-to-t from-zinc-950 via-zinc-950/40 to-transparent"></span>

            <span class="mb-3 inline-block w-fit -skew-x-12 bg-brand px-3 py-1 text-xs font-bold uppercase">
                <span class="block skew-x-12">{{ $listedPost->published_at->locale('de')->translatedFormat('j. F Y') }}</span>
            </span>

            <h3 class="break-words hyphens-auto font-black uppercase italic leading-tight {{ $loop->first ? 'text-3xl sm:text-4xl' : 'text-2xl' }}">{{ $listedPost->title }}</h3>

            <p class="mt-2 max-w-2xl text-white/80">{{ $listedPost->teaser(140) }}</p>
        </a>
    @endforeach
</div>
