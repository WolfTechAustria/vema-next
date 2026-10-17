@extends($templatePath.'.layout')

@section('content')
    @include($templatePath.'.section-heading', ['heading' => $page->title, 'asPageTitle' => true])

    @if($page->subtitle)
        <p class="-mt-4 mb-8 text-xl opacity-75">{{ $page->subtitle }}</p>
    @endif

    @if(filled($bodyHtml))
        <div class="mx-auto max-w-3xl">
            <div class="hp-content">{!! $bodyHtml !!}</div>
        </div>
    @endif

    @foreach($sections as $section)
        @include('homepage.sections._wrap')
    @endforeach
@endsection
