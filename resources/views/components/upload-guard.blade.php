@props(['maxKb'])

{{--
    Prüft die Dateigröße schon im Browser, bevor Livewire hochlädt: Zu große
    Dateien würde der Server sonst ohne verständliche Meldung verwerfen.
    Hört in der Capture-Phase, also vor wire:model am eingebetteten <input>.
--}}
@php
    $maxBytes = $maxKb * 1024;
    $maxLabel = \App\Services\UploadLimits::label($maxKb);
@endphp

<div
    {{ $attributes }}
    x-data="{ uploadTooLarge: '' }"
    x-on:change.capture="
        const tooLarge = [...($event.target.files || [])].filter(file => file.size > {{ $maxBytes }});
        uploadTooLarge = '';
        if (tooLarge.length) {
            $event.stopPropagation();
            uploadTooLarge = tooLarge.map(file => '„' + file.name + '“ (' + (file.size / 1048576).toFixed(1).replace('.', ',') + ' MB)').join(', ')
                + (tooLarge.length === 1 ? ' ist' : ' sind') + ' zu groß. Erlaubt sind höchstens {{ $maxLabel }} je Datei.';
            $event.target.value = '';
        }
    "
>
    {{ $slot }}

    <p x-show="uploadTooLarge" x-text="uploadTooLarge" x-cloak class="mt-1 text-xs text-red-600"></p>
</div>
