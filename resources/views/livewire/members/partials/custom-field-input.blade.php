{{--
    Eingabe für ein eigenes Mitgliederfeld ($field) — gebunden an customFields.<fieldID>.
--}}
<div wire:key="custom-field-{{ $field->fieldID }}">

    @if($field->type === \App\Enums\MemberFieldType::Boolean)
        <label class="flex items-center gap-3 sm:mt-7">
            <input
                type="checkbox"
                wire:model="customFields.{{ $field->fieldID }}"
                class="rounded border-slate-300"
            >

            <span class="text-sm font-medium text-slate-700">
                {{ $field->name }}
            </span>
        </label>
    @else
        <label class="mb-1 block text-sm font-medium text-slate-700">
            {{ $field->name }}
        </label>

        @if($field->type === \App\Enums\MemberFieldType::MultiSelect)
            <div class="flex flex-wrap gap-x-5 gap-y-2 py-1">
                @foreach($field->options ?? [] as $option)
                    <label class="flex items-center gap-2 text-sm text-slate-700">
                        <input type="checkbox" wire:model="customFields.{{ $field->fieldID }}" value="{{ $option }}" class="rounded border-slate-300">
                        {{ $option }}
                    </label>
                @endforeach
            </div>
        @elseif($field->type === \App\Enums\MemberFieldType::Select)
            <select
                wire:model="customFields.{{ $field->fieldID }}"
                class="w-full rounded-lg border border-slate-300 px-3 py-2"
            >
                <option value="">–</option>
                @foreach($field->options ?? [] as $option)
                    <option value="{{ $option }}">{{ $option }}</option>
                @endforeach
            </select>
        @else
            <input
                type="{{ $field->type === \App\Enums\MemberFieldType::Date ? 'date' : 'text' }}"
                @if($field->type === \App\Enums\MemberFieldType::Number) inputmode="decimal" @endif
                wire:model="customFields.{{ $field->fieldID }}"
                class="w-full rounded-lg border border-slate-300 px-3 py-2"
            >
        @endif
    @endif

    @error('customFields.'.$field->fieldID)
        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
    @enderror

</div>
