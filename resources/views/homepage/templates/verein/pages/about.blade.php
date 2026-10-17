@extends($templatePath.'.layout')

@section('content')
    @include($templatePath.'.page-head', ['eyebrow' => $pageTitle, 'heading' => $pageHeading])

    @if(filled($aboutHtml))
        <section class="py-[clamp(48px,8vw,80px)]">
            <div class="mx-auto max-w-[780px] px-4">
                <div class="hp-content hp-lead">{!! $aboutHtml !!}</div>
            </div>
        </section>
    @endif

    @foreach($sections as $section)
        @include('homepage.sections._wrap', ['fullBleed' => true])
    @endforeach
@endsection
