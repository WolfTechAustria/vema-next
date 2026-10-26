@props(['open' => false, 'titlePlaceholder' => '', 'descriptionPlaceholder' => ''])

{{--
    Eigener Titel und eigene Beschreibung für Suchmaschinen (Livewire-
    Eigenschaften seoTitle und seoDescription). Leer = automatisch.
--}}
<details wire:ignore.self {{ $attributes->merge(['class' => 'group rounded-lg border border-slate-200']) }} @if($open || $errors->hasAny(['seoTitle', 'seoDescription'])) open @endif>
    <summary class="flex cursor-pointer items-center justify-between px-4 py-3 text-sm font-medium text-slate-700">
        Suchmaschinen (Google)
        <span class="text-xs font-normal text-slate-400 group-open:hidden">optional</span>
    </summary>

    <div class="space-y-4 border-t border-slate-200 px-4 py-4" x-data="{ title: $wire.entangle('seoTitle'), description: $wire.entangle('seoDescription') }">
        <div>
            <label class="mb-1 flex justify-between text-sm font-medium">
                <span>Titel in Suchergebnissen</span>
                <span class="font-normal text-slate-400" :class="title.length > 60 && 'text-amber-600'" x-text="title.length + ' / 60'"></span>
            </label>
            <input type="text" x-model="title" maxlength="120" placeholder="{{ $titlePlaceholder }}" class="w-full rounded-lg border border-slate-300 px-3 py-2">
            <p class="mt-1 text-xs text-slate-500">Leer = Titel der Seite. Der Vereinsname wird automatisch angehängt.</p>
            @error('seoTitle')
            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label class="mb-1 flex justify-between text-sm font-medium">
                <span>Beschreibung in Suchergebnissen</span>
                <span class="font-normal text-slate-400" :class="description.length > 160 && 'text-amber-600'" x-text="description.length + ' / 160'"></span>
            </label>
            <textarea x-model="description" rows="2" maxlength="300" placeholder="{{ $descriptionPlaceholder }}" class="w-full rounded-lg border border-slate-300 px-3 py-2"></textarea>
            <p class="mt-1 text-xs text-slate-500">Ein bis zwei Sätze, die neugierig machen. Google zeigt etwa 160 Zeichen.</p>
            @error('seoDescription')
            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
            @enderror
        </div>
    </div>
</details>
