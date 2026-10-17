<div class="grid gap-8 sm:grid-cols-2 lg:grid-cols-3">
    @foreach($posts as $listedPost)
        <article class="group flex flex-col overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-100 transition hover:-translate-y-1 hover:shadow-lg">
            <a href="{{ route('homepage.posts.show', $listedPost->slug) }}" class="block aspect-[4/3] overflow-hidden bg-slate-100">
                @if($listedPost->coverImage)
                    <img src="{{ $imageUrls->postImage($listedPost->coverImage) }}" alt="" loading="lazy" class="h-full w-full object-cover transition duration-500 group-hover:scale-105">
                @else
                    <span class="flex h-full w-full items-center justify-center bg-gradient-to-br from-brand/80 to-brand text-4xl font-bold text-white/80">
                        {{ mb_substr(preg_replace('/[^\pL\pN]/u', '', $listedPost->title), 0, 1) }}
                    </span>
                @endif
            </a>

            <div class="flex flex-1 flex-col p-6">
                <p class="text-sm font-medium text-brand">{{ $listedPost->published_at->locale('de')->translatedFormat('j. F Y') }}</p>

                <h3 class="break-words hyphens-auto mt-2 text-xl font-bold text-slate-900">
                    <a href="{{ route('homepage.posts.show', $listedPost->slug) }}">{{ $listedPost->title }}</a>
                </h3>

                <p class="mt-3 flex-1 text-slate-600">{{ $listedPost->teaser(160) }}</p>
            </div>
        </article>
    @endforeach
</div>
