{{--
    Favicon aus dem Vereinslogo (ohne Logo: VEMA-Symbol), siehe ClubFavicon.
    Die Version im Link sorgt nach einem neuen Logo für ein frisches Symbol.
--}}
@php
    $faviconVersion = app(\App\Services\ClubFavicon::class)->version();
    $iconRoute ??= 'branding.icon';
    $faviconRoute ??= 'favicon';
@endphp
<link rel="icon" href="{{ route($faviconRoute, array_filter(['v' => $faviconVersion])) }}" sizes="any">
@if($faviconVersion)
    <link rel="icon" type="image/png" sizes="192x192" href="{{ route($iconRoute, ['size' => 192, 'v' => $faviconVersion]) }}">
    <link rel="apple-touch-icon" href="{{ route($iconRoute, ['size' => 180, 'v' => $faviconVersion]) }}">
@endif
