<section class="relative isolate overflow-hidden bg-zinc-900 text-white">
    @if($heroUrl)
        <img src="{{ $heroUrl }}" alt="" class="absolute inset-0 -z-10 h-full w-full object-cover opacity-50">
    @endif

    <div class="absolute -left-16 top-0 -z-10 h-full w-1/2 -skew-x-12 bg-brand/90"></div>

    <div class="mx-auto max-w-6xl px-4 py-20 sm:py-28">
        <h1 class="break-words hyphens-auto max-w-3xl text-4xl font-black uppercase italic leading-none tracking-tight sm:text-7xl">
            {{ $settings->hero_title ?: $club->name }}
        </h1>

        @if($settings->hero_text)
            <p class="mt-6 max-w-xl whitespace-pre-line text-lg font-medium text-white/85">{{ $settings->hero_text }}</p>
        @endif
    </div>
</section>
