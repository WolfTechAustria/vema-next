@extends($templatePath.'.layout')

@section('content')
    @include($templatePath.'.section-heading', ['heading' => $pageTitle, 'asPageTitle' => true])

    @include('homepage.partials.year-filter')

    @if($posts->isEmpty())
        <p class="opacity-70">Noch keine Beiträge vorhanden.</p>
    @else
        @include($templatePath.'.post-list', ['posts' => $posts])

        @if($posts->hasPages())
            <nav class="mt-12 flex items-center justify-between" aria-label="Seiten">
                @if($posts->onFirstPage())
                    <span></span>
                @else
                    <a href="{{ $posts->previousPageUrl() }}" class="font-semibold text-brand hover:underline">← Neuere Beiträge</a>
                @endif

                <span class="text-sm opacity-60">Seite {{ $posts->currentPage() }} von {{ $posts->lastPage() }}</span>

                @if($posts->hasMorePages())
                    <a href="{{ $posts->nextPageUrl() }}" class="font-semibold text-brand hover:underline">Ältere Beiträge →</a>
                @else
                    <span></span>
                @endif
            </nav>
        @endif
    @endif
@endsection
