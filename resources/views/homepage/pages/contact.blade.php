@extends($templatePath.'.layout')

@section('content')
    <div class="mx-auto max-w-3xl">
        @include($templatePath.'.section-heading', ['heading' => 'Kontakt', 'asPageTitle' => true])

        @include('homepage.partials.contact-details')
    </div>
@endsection
