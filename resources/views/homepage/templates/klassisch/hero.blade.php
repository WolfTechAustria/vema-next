@if($heroUrl || $settings->hero_title || $settings->hero_text)
    <section class="relative">
        @if($heroUrl)
            <img src="{{ $heroUrl }}" alt="" class="h-[42vh] min-h-64 w-full object-cover">
        @endif

        <div class="{{ $heroUrl ? 'relative mx-auto -mt-20 max-w-3xl px-4' : 'mx-auto max-w-3xl px-4 pt-14' }}">
            <div class="rounded-sm border-t-4 border-brand bg-white px-8 py-10 text-center shadow-lg">
                @if($settings->hero_title)
                    <h1 class="break-words hyphens-auto font-serif text-3xl font-bold text-stone-900 sm:text-4xl">{{ $settings->hero_title }}</h1>
                @endif

                @if($settings->hero_text)
                    <p class="mt-4 whitespace-pre-line text-lg leading-relaxed text-stone-600">{{ $settings->hero_text }}</p>
                @endif
            </div>
        </div>
    </section>
@endif
