<!DOCTYPE html>
<html lang="de">
<head>
    @include('homepage.partials.head')
</head>
<body class="bg-[#faf8f3] font-sans text-stone-800 antialiased">

@include('homepage.partials.preview-banner')

<header class="border-b border-stone-200 bg-white">
    <div class="mx-auto max-w-5xl px-4 py-6 text-center">
        <a href="{{ route('homepage.home') }}" class="inline-flex flex-col items-center gap-3">
            @if($logoUrl)
                <img src="{{ $logoUrl }}" alt="" class="h-16 w-auto">
            @endif

            <span class="font-serif text-3xl font-bold tracking-tight text-stone-900 sm:text-4xl">{{ $club->name }}</span>
        </a>
    </div>

    <nav class="border-t border-stone-200" aria-label="Hauptmenü">
        <ul class="mx-auto hidden max-w-5xl flex-wrap justify-center gap-x-8 px-4 py-3 text-sm font-semibold uppercase tracking-wider sm:flex">
            @foreach($navigation as $item)
                <li>
                    <a
                        href="{{ $item['url'] }}"
                        @if($item['active']) aria-current="page" @endif
                        class="border-b-2 pb-1 transition {{ $item['active'] ? 'border-brand text-brand' : 'border-transparent hover:text-brand' }}"
                    >{{ $item['label'] }}</a>
                </li>
            @endforeach
        </ul>

        <details class="px-4 py-3 sm:hidden">
            <summary class="cursor-pointer text-center text-sm font-semibold uppercase tracking-wider">Menü</summary>

            <ul class="mt-3 space-y-2 text-center">
                @foreach($navigation as $item)
                    <li><a href="{{ $item['url'] }}" class="{{ $item['active'] ? 'text-brand' : '' }} font-semibold">{{ $item['label'] }}</a></li>
                @endforeach
            </ul>
        </details>
    </nav>
</header>

@yield('hero')

<main class="mx-auto max-w-5xl px-4 py-14">
    @yield('content')
</main>

<footer class="border-t border-stone-200 bg-white">
    <div class="mx-auto max-w-5xl px-4 py-8 text-sm text-stone-500">
        @include('homepage.partials.footer-links')
    </div>
</footer>

</body>
</html>
