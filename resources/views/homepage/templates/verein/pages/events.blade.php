@extends($templatePath.'.layout')

@section('content')
    @include($templatePath.'.page-head', ['eyebrow' => $pageTitle, 'heading' => $pageHeading])

    <section class="py-[clamp(48px,8vw,80px)]">
        <div class="mx-auto max-w-[780px] px-4 [&_h2]:text-brand-deep [&_h3]:text-brand-deep">
            @include('homepage.partials.events-page-body')
        </div>
    </section>
@endsection
