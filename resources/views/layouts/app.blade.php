<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="utf-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <title>{{ $title ?? 'VEMA' }}</title>

    @vite([
        'resources/css/app.css',
        'resources/js/app.js'
    ])

    @livewireStyles
</head>


@php
    /*
    |--------------------------------------------------------------------------
    | Aktueller Benutzer
    |--------------------------------------------------------------------------
    |
    | web    = interne VEMA-Benutzer
    | member = Mitgliederportal
    |
    */

    $webUser = auth()->guard('web')->user();
    $memberAccount = auth()->guard('member')->user();

    $isMember = $memberAccount !== null;
    $isInternal = $webUser !== null;

    $portalMember = $memberAccount?->member;

    if ($isMember) {

        $displayName = $portalMember?->full_name
            ?: $memberAccount->email;

        $displaySubline = $memberAccount->email;

    } elseif ($isInternal) {

        $displayName =
            $webUser->member?->full_name
            ?? $webUser->username
            ?? 'Benutzer';

        $displaySubline =
            $webUser->username
            ?? '';

    } else {

        $displayName = 'VEMA';
        $displaySubline = '';

    }
@endphp


<body class="bg-slate-100 text-slate-900">

<div
    x-data="{ sidebarOpen: false }"
    class="min-h-screen"
>

    {{-- Mobile Overlay --}}
    <div
        x-show="sidebarOpen"
        x-transition.opacity
        x-cloak
        class="fixed inset-0 z-40 bg-black/50 lg:hidden"
        @click="sidebarOpen = false"
    ></div>


    {{-- Sidebar --}}
    <aside
        class="fixed inset-y-0 left-0 z-50 flex w-64 transform flex-col border-r border-slate-200 bg-white transition-transform duration-200 lg:translate-x-0"
        :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
    >

        {{-- Logo --}}
        <div class="flex h-16 shrink-0 items-center border-b border-slate-200 px-6">

            <div>

                <div class="text-xl font-bold tracking-tight text-slate-900">
                    VEMA
                </div>

                <div class="text-xs text-slate-500">

                    @if($isMember)
                        Mitgliederbereich
                    @else
                        Vereinsmanagement
                    @endif

                </div>

            </div>

        </div>


        {{-- Navigation --}}
        <nav class="flex-1 space-y-1 overflow-y-auto p-4">

            @if($isInternal)

                {{-- =====================================================
                     INTERNE VEMA-NAVIGATION
                ====================================================== --}}

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


                @if($webUser?->hasAnyRole(['admin', 'kassier']))
                    <a
                        href="{{ route('invoices.index') }}"
                        wire:navigate
                        class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition
                        {{ request()->routeIs('invoices.*', 'invoice-recipients.*', 'invoice-articles.*')
                            ? 'bg-slate-900 text-white'
                            : 'text-slate-700 hover:bg-slate-100' }}"
                    >
                        <span>▧</span>
                        Rechnungen
                    </a>
                @endif


                <div
                    x-data="{
        open: {{ request()->routeIs('circulars.*', 'recipient-groups.*', 'templates.*', 'external-contacts.*', 'skills.*') ? 'true' : 'false' }}
    }"
                    class="space-y-1"
                >

                    <button
                        type="button"
                        @click="open = !open"
                        class="flex w-full items-center justify-between rounded-lg px-3 py-2.5 text-sm font-medium transition
        {{ request()->routeIs('circulars.*', 'recipient-groups.*','templates.*', 'external-contacts.*', 'skills.*')
            ? 'bg-slate-900 text-white'
            : 'text-slate-700 hover:bg-slate-100' }}"
                    >
        <span class="flex items-center gap-3">
            <span>✉</span>
            Kommunikation
        </span>

                        <svg
                            class="h-4 w-4 transition-transform duration-200"
                            :class="open ? 'rotate-180' : ''"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M19 9l-7 7-7-7"
                            />
                        </svg>
                    </button>


                    <div
                        x-show="open"
                        x-collapse
                        x-cloak
                        class="space-y-1 pl-6"
                    >

                        <a
                            href="{{ route('circulars.index') }}"
                            wire:navigate
                            class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition
                            {{ request()->routeIs('circulars.*')
                                ? 'bg-slate-100 text-slate-900'
                                : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' }}"
                        >
                            <span>✉</span>
                            Rundschreiben
                        </a>


                        <a
                            href="{{ route('recipient-groups.index') }}"
                            wire:navigate
                            class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition
                            {{ request()->routeIs('recipient-groups.*')
                            ? 'bg-slate-100 text-slate-900'
                            : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' }}"
                        >
                            <span>♙</span>
                            Empfängergruppen
                        </a>

                        <a
                            href="{{ route('skills.index') }}"
                            wire:navigate
                            class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition
                            {{ request()->routeIs('skills.*')
                            ? 'bg-slate-100 text-slate-900'
                            : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' }}"
                        >
                            <span>★</span>
                            Fähigkeiten
                        </a>

                        <a
                            href="{{ route('external-contacts.index') }}"
                            wire:navigate
                            class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition
                            {{ request()->routeIs('external-contacts.*')
                                ? 'bg-slate-100 text-slate-900'
                                : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' }}"
                        >
                            <span>♙</span>
                            Externe Kontakte
                        </a>

                        <a
                            href="{{ route('templates.index') }}"
                            wire:navigate
                            class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition
                            {{ request()->routeIs('templates.*')
                            ? 'bg-slate-100 text-slate-900'
                            : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900'  }}"
                        >
                            <span>▤</span>
                            Templates
                        </a>

                    </div>

                </div>


                <a
                    href="#"
                    class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-slate-400"
                >
                    <span>◫</span>
                    Berichte
                </a>

            @elseif($isMember)

                {{-- =====================================================
                     MITGLIEDER-NAVIGATION
                ====================================================== --}}

                <div class="mb-2 px-3 pt-1 text-xs font-semibold uppercase tracking-wide text-slate-400">
                    Mitgliederbereich
                </div>


                <a
                    href="{{ route('member.profile') }}"
                    wire:navigate
                    class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition
                    {{ request()->routeIs('member.profile')
                        ? 'bg-slate-900 text-white'
                        : 'text-slate-700 hover:bg-slate-100' }}"
                >
                    <span>♙</span>
                    Mein Profil
                </a>


                {{-- Diese Bereiche bauen wir danach auf das eigene Mitglied beschränkt aus --}}

                <div
                    class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-slate-400"
                    title="Wird noch freigeschaltet"
                >
                    <span>€</span>
                    Meine Beiträge
                </div>


                <div
                    class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-slate-400"
                    title="Wird noch freigeschaltet"
                >
                    <span>✉</span>
                    Meine Rundschreiben
                </div>


                <a
                    href="{{ route('member.duties') }}"
                    wire:navigate
                    class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition
                    {{ request()->routeIs('member.duties')
                        ? 'bg-slate-900 text-white'
                        : 'text-slate-700 hover:bg-slate-100' }}"
                >
                    <span>▤</span>
                    Mein Dienstplan
                </a>


            @endif

        </nav>


        {{-- Benutzerbereich --}}
        <div class="shrink-0 border-t border-slate-200 p-4">

            <div class="mb-3 px-3">

                <div class="truncate text-sm font-semibold text-slate-900">
                    {{ $displayName }}
                </div>

                @if($displaySubline)

                    <div class="truncate text-xs text-slate-500">
                        {{ $displaySubline }}
                    </div>

                @endif

            </div>


            @if($isMember)

                {{-- Profil --}}
                <a
                    href="{{ route('member.profile') }}"
                    wire:navigate
                    class="mb-1 flex w-full items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100"
                >
                    <span>♙</span>
                    Mein Profil
                </a>

                @php
                    $memberProfileCount = 0;

                    if ($isMember && $memberAccount) {
                        $memberProfileCount = \App\Models\Member::query()
                            ->where('active', true)
                            ->whereHas('emails', function ($query) use ($memberAccount) {
                                $query->where('email', $memberAccount->email);
                            })
                            ->count();
                    }
                @endphp

                @if($isMember && $memberProfileCount > 1)

                    <a
                        href="{{ route('member.select-profile') }}"
                        class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm text-slate-600 hover:bg-slate-100 hover:text-slate-900"
                    >
                        <svg
                            class="h-5 w-5"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M17 20h5v-2a4 4 0 00-4-4h-1M9 20H4v-2a4 4 0 014-4h1m4 6v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2m9-10a4 4 0 110-8 4 4 0 010 8zm-6 0a4 4 0 110-8 4 4 0 010 8z"
                            />
                        </svg>

                        Profil wechseln
                    </a>

                @endif


                {{-- Einstellungen kommt als nächster Block --}}
                <button
                    type="button"
                    disabled
                    class="mb-1 flex w-full cursor-not-allowed items-center gap-2 rounded-lg px-3 py-2 text-left text-sm font-medium text-slate-400"
                    title="Kommt als nächstes"
                >
                    <span>⚙</span>
                    Einstellungen
                </button>


                @if(Route::has('member.logout'))

                    <form
                        method="POST"
                        action="{{ route('member.logout') }}"
                    >
                        @csrf

                        <button
                            type="submit"
                            class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-sm font-medium text-slate-700 hover:bg-slate-100"
                        >
                            <span>↪</span>
                            Abmelden
                        </button>

                    </form>

                @endif


            @elseif($isInternal)

                @if($webUser?->hasRole('admin'))

                    <a
                        href="{{ route('admin.settings.index') }}"
                        wire:navigate
                        class="mb-1 flex w-full items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium transition
                        {{ request()->routeIs('admin.*')
                            ? 'bg-slate-100 text-slate-900'
                            : 'text-slate-700 hover:bg-slate-100' }}"
                    >
                        <span>⚙</span>
                        Einstellungen
                    </a>

                @endif

                <form
                    method="POST"
                    action="{{ route('logout') }}"
                >
                    @csrf

                    <button
                        type="submit"
                        class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-sm font-medium text-slate-700 hover:bg-slate-100"
                    >
                        <span>↪</span>
                        Abmelden
                    </button>

                </form>

            @endif

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


            <div class="flex items-center gap-4">

                @if($isMember)

                    <span class="hidden rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-700 sm:inline-flex">
                        Mitgliederbereich
                    </span>

                @endif


                <div class="text-sm text-slate-500">
                    {{ now()->format('d.m.Y') }}
                </div>

            </div>

        </header>


        {{-- Page Content --}}
        <main class="p-4 sm:p-6 lg:p-8">

            {{ $slot }}

        </main>

    </div>

</div>


@livewireScripts


{{-- Global Loader --}}
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

        if (!loader) {
            return;
        }

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
