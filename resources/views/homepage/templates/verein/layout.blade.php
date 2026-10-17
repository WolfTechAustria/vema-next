@php
    // Vereinsname zweizeilig wie im Logo-Schriftzug: „Schützengilde / Angerberg“.
    $nameLead = str_contains($club->name, ' ') ? \Illuminate\Support\Str::beforeLast($club->name, ' ') : null;
    $nameMain = $nameLead ? \Illuminate\Support\Str::afterLast($club->name, ' ') : $club->name;
    $address = trim(implode(', ', array_filter([$club->street, trim($club->zip.' '.$club->city)])));
@endphp
<!DOCTYPE html>
<html lang="de">
<head>
    @include('homepage.partials.head')
</head>
<body class="hp-verein bg-white font-sans text-[17px] leading-[1.65] text-[#17201a] antialiased">

@include('homepage.partials.preview-banner')

<header class="sticky top-0 z-20 border-b border-[#dfe6e0] bg-white">
    <div class="relative mx-auto flex min-h-[76px] max-w-[1160px] items-center justify-between gap-4 px-4">
        <a href="{{ route('homepage.home') }}" class="flex min-w-0 items-center gap-3 text-[15px] leading-[1.15] text-[#17201a]">
            @if($logoUrl)
                <img src="{{ $logoUrl }}" alt="Logo {{ $club->name }}" width="52" height="52" class="h-[52px] w-[52px] flex-none object-contain">
            @endif

            <span class="min-w-0">
                @if($nameLead)
                    <span class="block truncate">{{ $nameLead }}</span>
                @endif
                <strong class="block truncate text-[1.2rem] text-brand-dark">{{ $nameMain }}</strong>
            </span>
        </a>

        <input type="checkbox" id="hp-nav-toggle" class="peer sr-only" aria-label="Menü öffnen">
        <label for="hp-nav-toggle" class="relative block h-[22px] w-[30px] cursor-pointer lg:hidden" aria-hidden="true">
            <span class="absolute inset-x-0 top-0 h-[3px] rounded bg-brand-dark"></span>
            <span class="absolute inset-x-0 top-[9px] h-[3px] rounded bg-brand-dark"></span>
            <span class="absolute inset-x-0 top-[18px] h-[3px] rounded bg-brand-dark"></span>
        </label>

        <nav
            aria-label="Hauptnavigation"
            class="absolute inset-x-0 top-[76px] hidden flex-col border-b border-[#dfe6e0] bg-white px-4 pb-4 pt-2 shadow-[0_12px_24px_rgba(0,0,0,.08)] peer-checked:flex lg:static lg:flex lg:flex-row lg:gap-0.5 lg:border-0 lg:p-0 lg:shadow-none"
        >
            @foreach($navigation as $item)
                <a
                    href="{{ $item['url'] }}"
                    @if($item['active']) aria-current="page" @endif
                    class="relative rounded-lg px-3 py-2.5 text-[15px] font-semibold transition hover:bg-brand-soft hover:text-brand-dark
                    {{ $item['active'] ? 'text-brand after:absolute after:inset-y-2.5 after:left-0 after:w-[3px] after:rounded after:bg-accent lg:after:inset-x-3 lg:after:bottom-1 lg:after:top-auto lg:after:h-[3px] lg:after:w-auto' : 'text-[#17201a]' }}"
                >{{ $item['label'] }}</a>
            @endforeach
        </nav>
    </div>
</header>

<main>
    @yield('content')
</main>

<footer class="bg-brand-deep pb-5 pt-12 text-[15px] text-[#b9cdbf]">
    <div class="mx-auto grid max-w-[1160px] gap-7 px-4 sm:grid-cols-2 lg:grid-cols-[1.3fr_1fr_1fr_1fr]">
        <div>
            @if($logoUrl)
                <img src="{{ $logoUrl }}" alt="" width="64" height="64" class="mb-3 h-16 w-16 rounded-full bg-white object-contain p-1.5">
            @endif

            <p>
                <strong class="text-white">{{ $club->name }}</strong>
                @if($address !== '')
                    <br>{{ $address }}
                @endif
            </p>
        </div>

        @if($club->phone || $club->email)
            <div>
                <h3 class="mb-2.5 text-[13px] font-semibold uppercase tracking-[.1em] text-white">Kontakt</h3>
                <p>
                    @if($club->phone)
                        <a href="tel:{{ preg_replace('/[^0-9+]/', '', $club->phone) }}" class="text-white hover:underline">{{ $club->phone }}</a><br>
                    @endif
                    @if($club->email)
                        <a href="mailto:{{ $club->email }}" class="break-all text-white hover:underline">{{ $club->email }}</a>
                    @endif
                </p>
            </div>
        @endif

        @if($settings->footer_info_title || $settings->footer_info_text)
            <div>
                <h3 class="mb-2.5 text-[13px] font-semibold uppercase tracking-[.1em] text-white">{{ $settings->footer_info_title ?: 'Info' }}</h3>
                <p class="whitespace-pre-line">{{ $settings->footer_info_text }}</p>
            </div>
        @endif

        @if($memberAreaUrl || $footerPages->isNotEmpty())
            <div>
                <h3 class="mb-2.5 text-[13px] font-semibold uppercase tracking-[.1em] text-white">Links</h3>
                <p>
                    @if($memberAreaUrl)
                        <a href="{{ $memberAreaUrl }}" class="text-white hover:underline">Mitgliederbereich</a><br>
                    @endif
                    @foreach($footerPages as $footerPage)
                        <a href="{{ route('homepage.page', $footerPage->slug) }}" class="text-white hover:underline">{{ $footerPage->title }}</a><br>
                    @endforeach
                </p>
            </div>
        @endif
    </div>

    <div class="mx-auto mt-8 max-w-[1160px] px-4">
        <div class="border-t border-white/10 pt-4 text-[13px]">© {{ now()->year }} {{ $club->name }}</div>
    </div>
</footer>

</body>
</html>
