@php
    $buttonUrl = $imageUrls->sectionButton($section);
    $buttonLabel = $section->value('button_label') ?: ($section->file_path ? 'PDF öffnen' : null);
@endphp

<div class="grid items-center gap-[clamp(28px,5vw,56px)] md:grid-cols-[1.1fr_1fr]">
    <div>
        @include('homepage.sections._heading')

        <div class="hp-content">{!! app(\App\Services\HtmlSanitizer::class)->sanitize($section->body_html) !!}</div>
    </div>

    @if($buttonUrl && $buttonLabel)
        <div>
            <a href="{{ $buttonUrl }}" @if($section->file_path) target="_blank" rel="noopener" @endif class="inline-block rounded-lg bg-brand px-6 py-3 text-[15px] font-bold text-white transition hover:-translate-y-px hover:bg-brand-dark">
                {{ $buttonLabel }}
            </a>
        </div>
    @endif
</div>
