<!DOCTYPE html>
<html lang="de">
<head>
    @include('homepage.partials.head')
</head>
<body class="bg-white font-sans text-slate-700 antialiased">

@include('homepage.partials.preview-banner')

<header class="sticky top-0 z-20 border-b border-slate-100 bg-white/90 backdrop-blur">
    <div class="mx-auto flex max-w-6xl items-center justify-between gap-6 px-4 py-4">
        <a href="{{ route('homepage.home') }}" class="flex min-w-0 items-center gap-3">
            @if($logoUrl)
                <img src="{{ $logoUrl }}" alt="" class="h-10 w-auto">
            @endif

            <span class="truncate text-lg font-bold text-slate-900">{{ $club->name }}</span>
        </a>

        <nav aria-label="Hauptmenü">
            <ul class="hidden items-center gap-1 text-sm font-medium lg:flex">
                @foreach($navigation as $item)
                    <li>
                        <a
                            href="{{ $item['url'] }}"
                            @if($item['active']) aria-current="page" @endif
                            class="rounded-full px-4 py-2 transition {{ $item['active'] ? 'bg-brand text-white' : 'hover:bg-slate-100' }}"
                        >{{ $item['label'] }}</a>
                    </li>
                @endforeach
            </ul>

            <details class="relative lg:hidden">
                <summary class="cursor-pointer list-none rounded-full border border-slate-200 px-4 py-2 text-sm font-medium">Menü</summary>

                <ul class="absolute right-0 mt-2 w-56 space-y-1 rounded-xl border border-slate-100 bg-white p-2 shadow-xl">
                    @foreach($navigation as $item)
                        <li>
                            <a href="{{ $item['url'] }}" class="block rounded-lg px-3 py-2 text-sm font-medium {{ $item['active'] ? 'bg-brand text-white' : 'hover:bg-slate-100' }}">{{ $item['label'] }}</a>
                        </li>
                    @endforeach
                </ul>
            </details>
        </nav>
    </div>
</header>

@yield('hero')

<main class="mx-auto max-w-6xl px-4 py-16">
    @yield('content')
</main>

<footer class="bg-slate-50">
    <div class="mx-auto max-w-6xl px-4 py-10 text-sm text-slate-500">
        @include('homepage.partials.footer-links')
    </div>
</footer>

</body>
</html>
