@php
    $buttonUrl = $imageUrls->sectionButton($section);
    $buttonLabel = $section->value('button_label') ?: ($section->file_path ? 'PDF öffnen' : null);
@endphp

<div class="grid items-center gap-[clamp(28px,5vw,56px)] md:grid-cols-[1.1fr_1fr]">
    <div class="hp-steps">
        @include('homepage.sections._heading')

        <div class="hp-content">{!! app(\App\Services\HtmlSanitizer::class)->sanitize($section->body_html) !!}</div>
    </div>

    <aside class="rounded-xl border border-[#dfe6e0] border-t-4 border-t-accent bg-white p-7 shadow-[0_1px_2px_rgba(0,0,0,.05),0_4px_16px_rgba(0,0,0,.06)]">
        @if($section->value('box_title'))
            <h3 class="mb-3 text-lg font-bold text-brand-deep">{{ $section->value('box_title') }}</h3>
        @endif

        @if($section->value('box_value'))
            <p class="text-[2.6rem] font-extrabold leading-tight text-brand-deep">
                {{ $section->value('box_value') }}
                @if($section->value('box_note'))
                    <span class="block text-base font-normal text-[#5b665e]">{{ $section->value('box_note') }}</span>
                @endif
            </p>
        @endif

        @if($section->value('box_text'))
            <p class="my-4 whitespace-pre-line">{{ $section->value('box_text') }}</p>
        @endif

        @if($buttonUrl && $buttonLabel)
            <a href="{{ $buttonUrl }}" @if($section->file_path) target="_blank" rel="noopener" @endif class="mt-2 block rounded-lg bg-brand px-6 py-3 text-center text-[15px] font-bold text-white transition hover:-translate-y-px hover:bg-brand-dark">
                {{ $buttonLabel }}
            </a>
        @endif
    </aside>
</div>
