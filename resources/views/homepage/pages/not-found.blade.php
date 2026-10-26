{{-- „Seite nicht gefunden“ (404) im Design der gewählten Vorlage. --}}
@extends($templatePath.'.layout')

@php
    $hasPageHead = view()->exists($templatePath.'.page-head');
@endphp

@section('content')
    @if($hasPageHead)
        @include($templatePath.'.page-head', ['eyebrow' => 'Fehler 404', 'heading' => 'Seite nicht gefunden'])
    @endif

    <div @class(['mx-auto max-w-[1160px] px-4 py-[clamp(48px,8vw,80px)]' => $hasPageHead])>
        <div class="mx-auto max-w-2xl text-center">
            <p class="select-none text-[clamp(5.5rem,20vw,10rem)] font-black leading-none tracking-tight text-brand/15" aria-hidden="true">404</p>

            @if($hasPageHead)
                <h2 class="-mt-4 text-[clamp(1.5rem,4vw,2.1rem)] font-bold leading-tight text-brand-deep">Diese Seite gibt es leider nicht.</h2>
            @else
                <h1 class="-mt-4 text-[clamp(1.6rem,4.5vw,2.4rem)] font-bold leading-tight">Diese Seite gibt es leider nicht.</h1>
            @endif

            <p class="mx-auto mt-4 max-w-xl text-lg opacity-75">
                Vielleicht wurde sie verschoben, oder in der Adresse hat sich ein Tippfehler eingeschlichen.
                Hier geht es weiter:
            </p>

            <div class="mt-8 flex flex-wrap justify-center gap-3">
                <a href="{{ route('homepage.home') }}" class="rounded-lg bg-brand px-5 py-3 font-semibold text-white transition hover:bg-brand-dark">Zur Startseite</a>
                <a href="{{ route('homepage.posts.index') }}" class="rounded-lg px-5 py-3 font-semibold text-brand ring-2 ring-inset ring-brand/30 transition hover:bg-brand-soft">{{ $settings->newsLabel() }}</a>

                @if($settings->contact_visible)
                    <a href="{{ route('homepage.contact') }}" class="rounded-lg px-5 py-3 font-semibold text-brand ring-2 ring-inset ring-brand/30 transition hover:bg-brand-soft">Kontakt</a>
                @endif
            </div>
        </div>

        @if($posts->isNotEmpty())
            <div class="mt-16">
                <h2 class="mb-6 text-center text-xl font-bold">Neueste Beiträge</h2>

                @include($templatePath.'.post-list', ['posts' => $posts])
            </div>
        @endif
    </div>
@endsection
