<section class="relative isolate overflow-hidden">
    @if($heroUrl)
        <img src="{{ $heroUrl }}" alt="" class="absolute inset-0 -z-10 h-full w-full object-cover">
        <div class="absolute inset-0 -z-10 bg-gradient-to-r from-slate-900/80 via-slate-900/50 to-transparent"></div>
    @else
        <div class="absolute inset-0 -z-10 bg-gradient-to-br from-brand to-slate-900"></div>
    @endif

    <div class="mx-auto max-w-6xl px-4 py-24 sm:py-32">
        <div class="max-w-2xl text-white">
            <h1 class="break-words hyphens-auto text-4xl font-extrabold tracking-tight sm:text-6xl">
                {{ $settings->hero_title ?: $club->name }}
            </h1>

            @if($settings->hero_text)
                <p class="mt-6 whitespace-pre-line text-lg leading-relaxed text-white/85">{{ $settings->hero_text }}</p>
            @endif

            <a href="{{ route('homepage.posts.index') }}" class="mt-8 inline-block rounded-full bg-white px-6 py-3 font-semibold text-slate-900 transition hover:bg-slate-100">
                Aktuelle News
            </a>
        </div>
    </div>
</section>
