@extends($templatePath.'.layout')

@php
    $shadow = 'shadow-[0_1px_2px_rgba(0,0,0,.05),0_4px_16px_rgba(0,0,0,.06)]';

    // Kacheln: eingetragene oder automatisch aus den Seiten im Menü.
    $tiles = collect($settings->tiles ?? [])->filter(fn (array $tile) => filled($tile['title'] ?? null))->values();

    if ($tiles->isEmpty()) {
        $tiles = collect($navigation)
            ->reject(fn (array $item) => $item['url'] === route('homepage.home') || $item['url'] === route('homepage.posts.index') || $item['url'] === route('homepage.contact'))
            ->take(3)
            ->map(fn (array $item) => ['kicker' => $item['label'], 'title' => $item['label'], 'text' => '', 'url' => $item['url'], 'link_label' => 'Mehr erfahren'])
            ->values();
    }

    $tileBorders = ['border-t-brand', 'border-t-accent', 'border-t-brand-dark'];
    $stats = collect($settings->stats ?? [])->filter(fn (array $stat) => filled($stat['value'] ?? null))->values();
    $announcementLink = $imageUrls->announcementLink($settings);
    $announcementImage = $imageUrls->announcementImage($settings);
    $latestPosts = $posts->take(3);
@endphp

@section('content')
    {{-- Titelbereich --}}
    <section class="relative isolate overflow-hidden py-[clamp(72px,12vw,128px)] text-white">
        @if($heroUrl)
            <img src="{{ $heroUrl }}" alt="" class="absolute inset-0 -z-20 h-full w-full object-cover">
        @endif
        <div class="absolute inset-0 -z-10 bg-[linear-gradient(100deg,color-mix(in_oklab,var(--color-brand-deep)_94%,transparent)_0%,color-mix(in_oklab,var(--color-brand-dark)_82%,transparent)_45%,color-mix(in_oklab,var(--color-brand-dark)_35%,transparent)_100%)]"></div>

        <div class="mx-auto max-w-[1160px] px-4">
            <p class="text-[13px] font-bold uppercase tracking-[.14em] text-[color-mix(in_oklab,var(--color-accent)_25%,white)]">{{ $settings->hero_eyebrow ?: $club->name }}</p>

            <h1 class="mb-[18px] mt-2.5 max-w-[720px] break-words hyphens-auto text-[clamp(2rem,5vw,3rem)] font-bold leading-[1.2] tracking-[-.01em]">
                {{ $settings->hero_title ?: 'Herzlich willkommen' }}
            </h1>

            @if($settings->hero_text)
                <p class="max-w-[600px] whitespace-pre-line text-[1.15rem] text-[color-mix(in_oklab,var(--color-brand-soft)_70%,white)]">{{ $settings->hero_text }}</p>
            @endif

            <div class="mt-8 flex flex-wrap gap-3">
                <a href="{{ route('homepage.posts.index') }}" class="rounded-lg bg-brand px-6 py-3 text-[15px] font-bold text-white transition hover:-translate-y-px hover:bg-brand-dark">Zu den Neuigkeiten</a>

                @if($settings->hero_button_label && $settings->hero_button_url)
                    <a href="{{ \App\Services\HomepageHosts::link($settings->hero_button_url) }}" class="rounded-lg px-6 py-3 text-[15px] font-bold text-white shadow-[inset_0_0_0_2px_rgba(255,255,255,.7)] transition hover:-translate-y-px hover:bg-white/10">{{ $settings->hero_button_label }}</a>
                @elseif($settings->contact_visible)
                    <a href="{{ route('homepage.contact') }}" class="rounded-lg px-6 py-3 text-[15px] font-bold text-white shadow-[inset_0_0_0_2px_rgba(255,255,255,.7)] transition hover:-translate-y-px hover:bg-white/10">Kontakt</a>
                @endif
            </div>
        </div>
    </section>

    {{-- Ankündigung --}}
    @if($settings->announcement_enabled && $settings->announcement_title)
        <section class="pt-[clamp(48px,8vw,80px)]">
            <div class="mx-auto max-w-[1160px] px-4">
                <a
                    @if($announcementLink) href="{{ $announcementLink }}" target="_blank" rel="noopener" @endif
                    class="grid items-center overflow-hidden rounded-xl border border-[#dfe6e0] bg-white {{ $shadow }} {{ $announcementImage ? 'md:grid-cols-[1.5fr_1fr]' : '' }}"
                >
                    @if($announcementImage)
                        <img src="{{ $announcementImage }}" alt="{{ $settings->announcement_title }}" class="h-full w-full object-cover">
                    @endif

                    <div class="p-7">
                        <span class="inline-block rounded bg-accent px-2.5 py-1 text-xs font-bold uppercase tracking-[.08em] text-white">{{ $settings->announcement_badge ?: 'Hinweis' }}</span>
                        <h2 class="mb-2 mt-2.5 text-[clamp(1.4rem,3vw,1.9rem)] font-bold leading-[1.2] text-brand-deep">{{ $settings->announcement_title }}</h2>

                        @if($settings->announcement_text)
                            <p class="mb-5 whitespace-pre-line text-[#5b665e]">{{ $settings->announcement_text }}</p>
                        @endif

                        @if($announcementLink)
                            <span class="inline-block rounded-lg bg-brand px-6 py-3 text-[15px] font-bold text-white">{{ $settings->announcement_link_label ?: 'Mehr erfahren' }}</span>
                        @endif
                    </div>
                </a>
            </div>
        </section>
    @endif

    {{-- Kacheln --}}
    @if($tiles->isNotEmpty())
        <section class="bg-[#f3f6f3] py-[clamp(48px,8vw,80px)] {{ $settings->announcement_enabled && $settings->announcement_title ? 'mt-[clamp(48px,8vw,80px)]' : '' }}">
            <div class="mx-auto grid max-w-[1160px] gap-6 px-4 md:grid-cols-3">
                @foreach($tiles as $tile)
                    <a
                        @if(filled($tile['url'] ?? null)) href="{{ \App\Services\HomepageHosts::link($tile['url']) }}" @endif
                        class="flex flex-col rounded-xl border-t-4 {{ $tileBorders[$loop->index] ?? 'border-t-brand' }} bg-white p-7 text-[#17201a] {{ $shadow }} transition hover:-translate-y-[3px] hover:shadow-[0_10px_28px_rgba(0,0,0,.1)]"
                    >
                        @if(filled($tile['kicker'] ?? null))
                            <span class="text-[13px] font-bold uppercase tracking-[.12em] text-[#5b665e]">{{ $tile['kicker'] }}</span>
                        @endif

                        <h2 class="mb-2.5 mt-1.5 text-[1.35rem] font-bold leading-[1.2] text-brand-deep">{{ $tile['title'] }}</h2>

                        <p class="mb-[18px] flex-1 text-[#5b665e]">{{ $tile['text'] ?? '' }}</p>

                        @if(filled($tile['url'] ?? null))
                            <span class="font-bold text-brand">{{ ($tile['link_label'] ?? '') ?: 'Mehr erfahren' }} →</span>
                        @endif
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    {{-- Aktuelles --}}
    <section class="py-[clamp(48px,8vw,80px)]">
        <div class="mx-auto max-w-[1160px] px-4">
            <div class="mb-7 flex items-baseline justify-between gap-4">
                <h2 class="text-[clamp(1.4rem,3vw,1.9rem)] font-bold leading-[1.2] text-brand-deep">{{ $settings->newsLabel() }}</h2>
                <a href="{{ route('homepage.posts.index') }}" class="font-bold text-brand">Alle Beiträge →</a>
            </div>

            @if($latestPosts->isEmpty())
                <p class="text-[#5b665e]">Noch keine Beiträge vorhanden.</p>
            @else
                @include($templatePath.'.post-list', ['posts' => $latestPosts])
            @endif
        </div>
    </section>

    {{-- Kennzahlen --}}
    @if($stats->isNotEmpty())
        <section class="bg-brand-deep py-[clamp(48px,8vw,80px)] text-[color-mix(in_oklab,var(--color-brand-soft)_70%,white)]">
            <div class="mx-auto grid max-w-[1160px] grid-cols-2 gap-6 px-4 text-center {{ [1 => 'md:grid-cols-1', 2 => 'md:grid-cols-2', 3 => 'md:grid-cols-3'][$stats->count()] ?? 'md:grid-cols-4' }}">
                @foreach($stats as $stat)
                    <div>
                        <strong class="block text-[clamp(2rem,5vw,2.8rem)] leading-[1.1] text-white">{{ $stat['value'] }}</strong>
                        <span class="text-[15px]">{{ $stat['label'] ?? '' }}</span>
                    </div>
                @endforeach
            </div>
        </section>
    @endif
@endsection
