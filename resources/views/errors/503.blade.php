{{--
    Wartungsseite (HTTP 503) im Stil von vemat.at.

    Bewusst ohne @vite, Datenbank oder Session: Während "php artisan down"
    soll die Seite auch dann funktionieren, wenn Assets gerade neu gebaut
    werden. Mit "php artisan down --render=errors::503" wird sie vorab
    gerendert und ohne Framework ausgeliefert.
--}}
@php($websiteUrl = rtrim(config('tenancy.website_url', 'https://vemat.at'), '/'))
<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">

    <title>Wartungsarbeiten | vemat</title>

    @include('errors.partials.vemat-styles')
</head>

<body>

<a href="{{ $websiteUrl }}" class="logo" aria-label="vemat Startseite">
    <svg viewBox="0 0 40 40" aria-hidden="true">
        <rect width="40" height="40" rx="10" fill="#0f766e"></rect>
        <path d="M10 13l10 16 10-16" fill="none" stroke="#fff" stroke-width="4.2" stroke-linecap="round" stroke-linejoin="round"></path>
        <circle class="dot" cx="30" cy="11" r="3.4" fill="#f59e0b"></circle>
    </svg>
    vemat
</a>

<main class="card">

    <div class="icon" aria-hidden="true">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"></path>
        </svg>
    </div>

    <p class="eyebrow">Wartungsarbeiten</p>

    <h1>Wir sind gleich wieder da.</h1>

    <p class="lead">
        Wir spielen gerade ein Update ein, damit vemat für euren Verein
        noch besser läuft. Eure Daten sind sicher.
    </p>

    <div class="status" role="status">Update läuft</div>

    <p class="hint">
        Das dauert in der Regel nur wenige Minuten. Bitte lade die Seite
        gleich noch einmal.
    </p>

    <div class="actions">
        <a href="" class="btn">Seite neu laden</a>
    </div>

</main>

<footer>
    <a href="{{ $websiteUrl }}">vemat · Vereinsmanagement aus Österreich</a>
    ·
    <a href="{{ $websiteUrl }}/impressum.html">Impressum</a>
    ·
    <a href="{{ $websiteUrl }}/datenschutz.html">Datenschutz</a>
</footer>

</body>

</html>
