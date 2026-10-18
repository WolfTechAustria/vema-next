@extends($templatePath.'.layout')

@section('content')
    <div class="mx-auto max-w-3xl">
        @include($templatePath.'.section-heading', ['heading' => $pageTitle, 'asPageTitle' => true])

        @include('homepage.partials.events-page-body')
    </div>
@endsection
