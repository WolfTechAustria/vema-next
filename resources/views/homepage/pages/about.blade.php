@extends($templatePath.'.layout')

@section('content')
    @include($templatePath.'.section-heading', ['heading' => 'Über uns', 'asPageTitle' => true])

    @if(filled($aboutHtml))
        <div class="mx-auto max-w-3xl">
            <div class="hp-content">{!! $aboutHtml !!}</div>
        </div>
    @endif

    @foreach($sections as $section)
        @include('homepage.sections._wrap')
    @endforeach
@endsection
