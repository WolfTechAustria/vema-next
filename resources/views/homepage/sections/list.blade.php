{{-- Titel mit Aufzählung: nummerierte Schritte, Punkte oder Häkchen. --}}
@php
    $listItems = collect($section->value('list_items', []))->filter(fn ($item) => filled($item['title'] ?? null) || filled($item['text'] ?? null));
    $listStyle = $section->value('list_style', 'numbered');
@endphp

<div class="{{ ($inColumn ?? false) ? '' : 'mx-auto max-w-[780px]' }}">
    @include('homepage.sections._heading')

    @if(filled($section->body_html))
        <div class="hp-content mb-4">{!! app(\App\Services\HtmlSanitizer::class)->sanitize($section->body_html) !!}</div>
    @endif

    @if($listItems->isNotEmpty())
        <ol class="hp-list hp-list--{{ $listStyle }}">
            @foreach($listItems as $listItem)
                @php($itemTitle = rtrim((string) ($listItem['title'] ?? ''), ': '))
                <li>
                    @if($itemTitle !== '')
                        <strong>{{ $itemTitle }}{{ filled($listItem['text'] ?? null) ? ':' : '' }}</strong>
                    @endif
                    {{ $listItem['text'] ?? '' }}
                </li>
            @endforeach
        </ol>
    @endif
</div>
