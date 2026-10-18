<div class="grid items-center gap-[clamp(28px,5vw,56px)] md:grid-cols-[1.1fr_1fr]">
    <div class="hp-steps">
        @include('homepage.sections._heading')

        <div class="hp-content">{!! app(\App\Services\HtmlSanitizer::class)->sanitize($section->body_html) !!}</div>
    </div>

    @include('homepage.sections._box')
</div>
