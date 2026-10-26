@php
    $titlePart = $seoTitle ?? $pageTitle ?? null;
    $documentTitle = $titlePart ? $titlePart.' | '.$club->name : $club->name;
    $shareImage = $ogImage ?? $heroUrl;
@endphp
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $documentTitle }}</title>

@if($metaDescription)
    <meta name="description" content="{{ $metaDescription }}">
@endif

@if($isPreview || ! $settings->enabled)
    <meta name="robots" content="noindex, nofollow">
@endif

@if($canonicalUrl ?? null)
    <link rel="canonical" href="{{ $canonicalUrl }}">
    <meta property="og:url" content="{{ $canonicalUrl }}">
@endif

<meta property="og:type" content="{{ isset($post) ? 'article' : 'website' }}">
<meta property="og:locale" content="de_AT">
<meta property="og:title" content="{{ $documentTitle }}">
<meta property="og:site_name" content="{{ $club->name }}">
@if($metaDescription)
    <meta property="og:description" content="{{ $metaDescription }}">
@endif
@if($shareImage)
    <meta property="og:image" content="{{ $shareImage }}">
@endif
<meta name="twitter:card" content="{{ $shareImage ? 'summary_large_image' : 'summary' }}">

@foreach($structuredData ?? [] as $structuredItem)
    <script type="application/ld+json">{!! json_encode($structuredItem, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
@endforeach

@if($logoUrl)
    <link rel="icon" href="{{ $logoUrl }}">
@endif

@vite(['resources/css/homepage.css', 'resources/js/homepage.js'])

<style>:root { --hp-brand: {{ $settings->primary_color }}; --hp-accent: {{ $settings->accent_color }}; }</style>
