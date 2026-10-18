@props(['field', 'maxKb'])

{{--
    Fehler eines Datei-Uploads. Scheitert der Upload schon am Server (zu groß
    für php.ini oder das Livewire-Limit), kommt nur ein Platzhalter wie
    „validation.uploaded“ an – dann eine verständliche Meldung zeigen.
--}}
@php
    $message = $errors->first($field) ?: $errors->first($field.'.*');

    if ($message && preg_match('/validation\.uploaded|failed to upload|kilobytes|kilobyte/i', $message)) {
        $message = 'Die Datei konnte nicht hochgeladen werden – vermutlich ist sie zu groß. Erlaubt sind höchstens '.\App\Services\UploadLimits::label($maxKb).' je Datei.';
    }
@endphp

@if($message)
    <p {{ $attributes->merge(['class' => 'mt-1 text-xs text-red-600']) }}>{{ $message }}</p>
@endif
