<div class="grid gap-6 sm:grid-cols-[repeat(auto-fill,minmax(300px,1fr))]">
    @foreach($posts as $listedPost)
        <article class="overflow-hidden rounded-xl border border-[#dfe6e0] bg-white shadow-[0_1px_2px_rgba(0,0,0,.05),0_4px_16px_rgba(0,0,0,.06)] transition hover:-translate-y-[3px] hover:shadow-[0_10px_28px_rgba(0,0,0,.1)]">
            <a href="{{ route('homepage.posts.show', $listedPost->slug) }}" class="flex h-full flex-col">
                @if($listedPost->coverImage)
                    <img src="{{ $imageUrls->postImage($listedPost->coverImage) }}" alt="" loading="lazy" class="aspect-[16/10] w-full bg-[#f3f6f3] object-cover">
                @else
                    <span class="grid aspect-[16/10] w-full place-items-center bg-brand-soft" aria-hidden="true">
                        @if($logoUrl)
                            <img src="{{ $logoUrl }}" alt="" class="w-[38%] opacity-85">
                        @endif
                    </span>
                @endif

                <span class="flex flex-1 flex-col px-[22px] pb-[22px] pt-5">
                    <time datetime="{{ $listedPost->published_at->toDateString() }}" class="text-sm font-bold tracking-wide text-accent">
                        {{ $listedPost->published_at->locale('de')->translatedFormat('j. F Y') }}
                    </time>

                    <h3 class="mb-2 mt-1 break-words hyphens-auto text-xl font-bold leading-tight text-brand-deep">{{ $listedPost->title }}</h3>

                    <span class="mb-3.5 block text-[15px] text-[#5b665e]">{{ $listedPost->teaser(160) }}</span>

                    <span class="mt-auto font-bold text-brand">Weiterlesen →</span>
                </span>
            </a>
        </article>
    @endforeach
</div>
