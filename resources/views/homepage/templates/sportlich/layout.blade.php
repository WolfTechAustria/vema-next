<!DOCTYPE html>
<html lang="de">
<head>
    @include('homepage.partials.head')
</head>
<body class="bg-zinc-100 font-sans text-zinc-800 antialiased">

@include('homepage.partials.preview-banner')

<header class="bg-zinc-950 text-white">
    <div class="h-1.5 bg-brand"></div>

    <div class="mx-auto flex max-w-6xl items-center justify-between gap-6 px-4 py-4">
        <a href="{{ route('homepage.home') }}" class="flex min-w-0 items-center gap-3">
            @if($logoUrl)
                <span class="flex h-12 w-12 flex-none items-center justify-center rounded-full bg-white p-1">
                    <img src="{{ $logoUrl }}" alt="" class="max-h-full w-auto">
                </span>
            @endif

            <span class="truncate text-xl font-black uppercase italic tracking-tight">{{ $club->name }}</span>
        </a>

        <nav aria-label="Hauptmenü">
            <ul class="hidden items-center text-sm font-bold uppercase tracking-wide lg:flex">
                @foreach($navigation as $item)
                    <li>
                        <a
                            href="{{ $item['url'] }}"
                            @if($item['active']) aria-current="page" @endif
                            class="block -skew-x-12 px-4 py-2 transition {{ $item['active'] ? 'bg-brand' : 'hover:bg-white/10' }}"
                        ><span class="block skew-x-12">{{ $item['label'] }}</span></a>
                    </li>
                @endforeach
            </ul>

            <details class="relative lg:hidden">
                <summary class="cursor-pointer list-none bg-brand px-4 py-2 text-sm font-bold uppercase">Menü</summary>

                <ul class="absolute right-0 z-20 mt-2 w-56 bg-zinc-900 p-2 shadow-xl">
                    @foreach($navigation as $item)
                        <li>
                            <a href="{{ $item['url'] }}" class="block px-3 py-2 text-sm font-bold uppercase {{ $item['active'] ? 'bg-brand' : 'hover:bg-white/10' }}">{{ $item['label'] }}</a>
                        </li>
                    @endforeach
                </ul>
            </details>
        </nav>
    </div>
</header>

@yield('hero')

<main class="mx-auto max-w-6xl px-4 py-14">
    @yield('content')
</main>

<footer class="bg-zinc-950 text-zinc-400">
    <div class="mx-auto max-w-6xl px-4 py-10 text-sm">
        @include('homepage.partials.footer-links')
    </div>
</footer>

@include('homepage.partials.notices')

</body>
</html>
