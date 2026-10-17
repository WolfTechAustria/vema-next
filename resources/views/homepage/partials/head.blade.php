@php
    $documentTitle = $pageTitle ? $pageTitle.' | '.$club->name : $club->name;
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

<meta property="og:type" content="{{ isset($post) ? 'article' : 'website' }}">
<meta property="og:title" content="{{ $documentTitle }}">
<meta property="og:site_name" content="{{ $club->name }}">
@if($metaDescription)
    <meta property="og:description" content="{{ $metaDescription }}">
@endif
@if($ogImage ?? $heroUrl)
    <meta property="og:image" content="{{ $ogImage ?? $heroUrl }}">
@endif

@if($logoUrl)
    <link rel="icon" href="{{ $logoUrl }}">
@endif

@vite(['resources/css/homepage.css', 'resources/js/homepage.js'])

<style>:root { --hp-brand: {{ $settings->primary_color }}; --hp-accent: {{ $settings->accent_color }}; }</style>
