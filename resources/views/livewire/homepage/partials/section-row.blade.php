{{-- Eine Zeile der Abschnittsliste (oberste Ebene oder in einer Spalte). --}}
<div class="flex items-start justify-between gap-3 {{ $editingSectionID === $section->sectionID ? 'rounded-md bg-slate-100' : '' }} {{ $inColumn ? 'px-2 py-1.5' : '' }}">
    <div class="min-w-0">
        <div class="text-xs font-semibold uppercase tracking-wide text-slate-400">
            {{ $label }}{{ $section->type->label() }}
            @if($section->alt_background) · grauer Hintergrund @endif
            @if($section->value('spacing')) · Abstand {{ $spacings[$section->value('spacing')] ?? '' }} @endif
            @if($section->value('box_frameless')) · ohne Rahmen @endif
        </div>
        <div class="truncate {{ $inColumn ? 'text-sm' : '' }} font-medium text-slate-900">
            {{ $section->heading ?: ($section->value('box_title') ?: (\Illuminate\Support\Str::limit(strip_tags((string) $section->body_html), 50) ?: ($section->type->isContainer() ? ($ratios[$section->value('ratio', '1-1')] ?? '') : '–'))) }}
        </div>
    </div>

    <div class="flex flex-none items-center gap-2 text-sm">
        @unless($isFirst)
            <button type="button" wire:click="moveSection({{ $section->sectionID }}, 'up')" class="text-slate-500 hover:text-slate-800" title="Nach oben">↑</button>
        @endunless
        @unless($isLast)
            <button type="button" wire:click="moveSection({{ $section->sectionID }}, 'down')" class="text-slate-500 hover:text-slate-800" title="Nach unten">↓</button>
        @endunless
        @if($inColumn)
            <button type="button" wire:click="switchColumn({{ $section->sectionID }})" class="text-slate-500 hover:text-slate-800" title="In die andere Spalte">⇄</button>
        @endif
        <button type="button" wire:click="editSection({{ $section->sectionID }})" class="font-medium text-slate-600 hover:text-slate-900">Bearbeiten</button>
        <button
            type="button"
            wire:click="deleteSection({{ $section->sectionID }})"
            wire:confirm="{{ $section->type->isContainer() ? 'Spalten samt allen Abschnitten darin löschen?' : 'Abschnitt wirklich löschen?' }}"
            class="font-medium text-red-600 hover:text-red-800"
        >Löschen</button>
    </div>
</div>
