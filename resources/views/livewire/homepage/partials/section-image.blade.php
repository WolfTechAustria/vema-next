<div>
    <label class="mb-1 block text-sm font-medium">{{ $label }}</label>

    <div class="flex items-center gap-3">
        @if($this->{$field})
            <img src="{{ $this->{$field}->temporaryUrl() }}" alt="" class="h-16 w-24 rounded-lg object-cover">
        @elseif($url)
            <img src="{{ $url }}" alt="" class="h-16 w-24 rounded-lg object-cover">
        @endif

        <input type="file" wire:model="{{ $field }}" accept="image/jpeg,image/png,image/webp" class="text-sm">

        @if($url && ! $this->{$field})
            <button type="button" wire:click="removeMedia('{{ $field }}')" class="text-sm text-red-600 hover:text-red-800">Entfernen</button>
        @endif
    </div>

    <span wire:loading wire:target="{{ $field }}" class="text-xs text-slate-500">Wird hochgeladen …</span>

    @error($field) <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
</div>
