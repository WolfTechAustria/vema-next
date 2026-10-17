<div class="mx-auto max-w-[780px]">
    @include('homepage.sections._heading')

    <div class="hp-content">{!! app(\App\Services\HtmlSanitizer::class)->sanitize($section->body_html) !!}</div>
</div>
