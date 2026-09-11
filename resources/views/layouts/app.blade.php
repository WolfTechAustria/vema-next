<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>{{ $title ?? 'VEMA' }}</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>

<body class="bg-slate-100 text-slate-900">

<div
    x-data="{ sidebarOpen: false }"
    class="min-h-screen"
>
    {{-- Mobile Overlay --}}
    <div
        x-show="sidebarOpen"
        x-transition.opacity
        class="fixed inset-0 z-40 bg-black/50 lg:hidden"
        @click="sidebarOpen = false"
    ></div>

    {{-- Sidebar --}}
    <aside
        class="fixed inset-y-0 left-0 z-50 w-64 transform border-r border-slate-200 bg-white transition-transform duration-200 lg:translate-x-0"
        :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
    >
        <div class="flex h-16 items-center border-b border-slate-200 px-6">
            <div>
                <div class="text-xl font-bold tracking-tight text-slate-900">
                    VEMA
                </div>

                <div class="text-xs text-slate-500">
                    Vereinsmanagement
                </div>
            </div>
        </div>

        <nav class="space-y-1 p-4">

            <a
                href="{{ route('dashboard') }}"
                wire:navigate
                class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition
                {{ request()->routeIs('dashboard')
                    ? 'bg-slate-900 text-white'
                    : 'text-slate-700 hover:bg-slate-100' }}"
            >
                <span>▦</span>
                Dashboard
            </a>

            <a
                href="{{ route('members.index') }}"
                wire:navigate
                class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition
                {{ request()->routeIs('members.*')
                    ? 'bg-slate-900 text-white'
                    : 'text-slate-700 hover:bg-slate-100' }}"
            >
                <span>♙</span>
                Mitglieder
            </a>

            <a
                href="{{ route('duty-plan.index') }}"
                wire:navigate
                class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition
                {{ request()->routeIs('duty-plan.*')
                    ? 'bg-slate-900 text-white'
                    : 'text-slate-700 hover:bg-slate-100' }}"
            >
                <span>▤</span>
                Dienstplan
            </a>

            <a
                href="{{ route('membership-fees.index') }}"
                wire:navigate
                class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition
    {{ request()->routeIs('membership-fees.*')
        ? 'bg-slate-900 text-white'
        : 'text-slate-700 hover:bg-slate-100' }}"
            >
                <span>€</span>
                Mitgliedsbeiträge
            </a>

            <a
                href="#"
                class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-slate-400"
            >
                <span>▣</span>
                Finanzen
            </a>

            <a
                href="#"
                class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-slate-400"
            >
                <span>▧</span>
                Rechnungen
            </a>

            <a
                href="{{ route('circulars.index') }}"
                wire:navigate
                class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition
                {{ request()->routeIs('templates.*')
                    ? 'bg-slate-900 text-white'
                    : 'text-slate-700 hover:bg-slate-100' }}"
            >
                <span>✉</span>
                Kommunikation
            </a>
            <a
                href="#"
                class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-slate-400"
            >
                <span>◫</span>
                Berichte
            </a>

            <a
                href="{{ route('templates.index') }}"
                wire:navigate
                class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition
                {{ request()->routeIs('templates.*')
                    ? 'bg-slate-900 text-white'
                    : 'text-slate-700 hover:bg-slate-100' }}"
            >
                <span>▤</span>
                Templates
            </a>

        </nav>

        <div class="absolute bottom-0 left-0 right-0 border-t border-slate-200 p-4">

            <div class="mb-3 px-3">
                <div class="text-sm font-semibold text-slate-900">
                    {{ auth()->user()->member?->full_name ?? auth()->user()->username }}
                </div>

                <div class="text-xs text-slate-500">
                    {{ auth()->user()->username }}
                </div>
            </div>

            <form method="POST" action="{{ route('logout') }}">
                @csrf

                <button
                    type="submit"
                    class="w-full rounded-lg px-3 py-2 text-left text-sm font-medium text-slate-700 hover:bg-slate-100"
                >
                    Abmelden
                </button>
            </form>

        </div>
    </aside>

    {{-- Main Area --}}
    <div class="lg:pl-64">

        {{-- Topbar --}}
        <header class="sticky top-0 z-30 flex h-16 items-center border-b border-slate-200 bg-white/95 px-4 backdrop-blur sm:px-6 lg:px-8">

            <button
                type="button"
                class="mr-4 rounded-lg p-2 text-slate-600 hover:bg-slate-100 lg:hidden"
                @click="sidebarOpen = true"
            >
                ☰
            </button>

            <div class="flex-1">
                <h1 class="text-lg font-semibold">
                    {{ $heading ?? 'VEMA' }}
                </h1>
            </div>

            <div class="text-sm text-slate-500">
                {{ now()->format('d.m.Y') }}
            </div>

        </header>

        {{-- Page Content --}}
        <main class="p-4 sm:p-6 lg:p-8">
            {{ $slot }}
        </main>

    </div>

</div>



@livewireScripts

<div
    id="global-loader"
    class="fixed inset-0 z-[9999] hidden items-center justify-center bg-slate-900/30 backdrop-blur-sm"
>
    <div class="flex items-center gap-3 rounded-xl bg-white px-6 py-4 shadow-xl">
        <svg
            class="h-5 w-5 animate-spin text-slate-700"
            xmlns="http://www.w3.org/2000/svg"
            fill="none"
            viewBox="0 0 24 24"
        >
            <circle
                class="opacity-25"
                cx="12"
                cy="12"
                r="10"
                stroke="currentColor"
                stroke-width="4"
            ></circle>

            <path
                class="opacity-75"
                fill="currentColor"
                d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"
            ></path>
        </svg>

        <span class="text-sm font-medium text-slate-700">
            Bitte warten …
        </span>
    </div>
</div>
<script>
    document.addEventListener('livewire:init', () => {

        let activeRequests = 0;

        const loader = document.getElementById('global-loader');

        Livewire.interceptRequest(({ onSend, onFinish }) => {

            onSend(() => {
                activeRequests++;

                loader.classList.remove('hidden');
                loader.classList.add('flex');
            });

            onFinish(() => {
                activeRequests--;

                if (activeRequests <= 0) {
                    activeRequests = 0;

                    loader.classList.add('hidden');
                    loader.classList.remove('flex');
                }
            });

        });

    });
</script>
</body>
</html>
