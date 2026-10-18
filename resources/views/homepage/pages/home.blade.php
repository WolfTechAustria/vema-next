@extends($templatePath.'.layout')

@section('hero')
    @include($templatePath.'.hero')
@endsection

@section('content')
    @if($upcomingEvents->isNotEmpty())
        <section class="mb-16">
            @include($templatePath.'.section-heading', ['heading' => 'Nächste Termine'])

            <div class="mx-auto max-w-3xl">
                @include('homepage.partials.event-list', ['events' => $upcomingEvents, 'compact' => true])

                <p class="mt-6 text-center"><a href="{{ route('homepage.events') }}" class="font-semibold text-brand hover:underline">Alle Termine →</a></p>
            </div>
        </section>
    @endif

    <section>
        @include($templatePath.'.section-heading', ['heading' => 'Aktuelles'])

        @if($posts->isEmpty())
            <p class="opacity-70">Hier erscheinen bald die ersten Neuigkeiten.</p>
        @else
            @include($templatePath.'.post-list', ['posts' => $posts])

            <div class="mt-10 text-center">
                <a href="{{ route('homepage.posts.index') }}" class="inline-block rounded-full border-2 border-brand px-6 py-2 font-semibold text-brand transition hover:bg-brand hover:text-white">
                    Alle News
                </a>
            </div>
        @endif
    </section>
@endsection
