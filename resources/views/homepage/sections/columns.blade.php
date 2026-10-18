{{-- Zwei Spalten mit eigenen Abschnitten (eine Ebene). --}}
@php
    $gridClass = [
        '2-1' => 'md:grid-cols-[2fr_1fr]',
        '1-2' => 'md:grid-cols-[1fr_2fr]',
    ][$section->value('ratio', '1-1')] ?? 'md:grid-cols-2';

    $alignClass = $section->value('align') === 'center' ? 'items-center' : 'items-start';
    $columnSections = $section->relationLoaded('children') ? $section->children : $section->children()->get();
@endphp

<div class="grid gap-[clamp(28px,5vw,56px)] {{ $gridClass }} {{ $alignClass }}">
    @foreach(['left', 'right'] as $columnKey)
        <div class="min-w-0 space-y-10">
            @foreach($columnSections->where('column', $columnKey) as $child)
                @include('homepage.sections.'.$child->type->value, ['section' => $child, 'inColumn' => true])
            @endforeach
        </div>
    @endforeach
</div>
