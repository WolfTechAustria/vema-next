@extends($templatePath.'.layout')

@section('content')
    @include($templatePath.'.page-head', ['eyebrow' => $pageTitle, 'heading' => $pageHeading])

    <section class="py-[clamp(48px,8vw,80px)]">
        <div class="mx-auto max-w-[1160px] px-4">
            @include('homepage.partials.year-filter')

            @if($posts->isEmpty())
                <p class="text-[#5b665e]">Noch keine Beiträge vorhanden.</p>
            @else
                @include($templatePath.'.post-list', ['posts' => $posts])
            @endif

            @if($posts->hasPages())
                <nav class="mt-10 flex flex-wrap justify-center gap-2" aria-label="Seiten">
                    @foreach(range(1, $posts->lastPage()) as $pageNumber)
                        <a
                            href="{{ $posts->url($pageNumber) }}"
                            @if($pageNumber === $posts->currentPage()) aria-current="page" @endif
                            class="rounded-full px-3.5 py-1.5 text-sm font-semibold {{ $pageNumber === $posts->currentPage() ? 'bg-brand text-white' : 'bg-[#f3f6f3] text-[#17201a]' }}"
                        >{{ $pageNumber }}</a>
                    @endforeach
                </nav>
            @endif
        </div>
    </section>
@endsection
