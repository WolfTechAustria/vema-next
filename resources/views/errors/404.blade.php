{{--
    Seite nicht gefunden (HTTP 404) im Stil von vemat.at — wie die
    Wartungsseite ohne @vite. Auf der Vereinshomepage erscheint stattdessen
    die 404-Seite im Design der Homepage (HomepageController::notFoundResponse).
--}}
@php($websiteUrl = rtrim(config('tenancy.website_url', 'https://vemat.at'), '/'))
<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">

    <title>Seite nicht gefunden | vemat</title>

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

    <p class="code" aria-hidden="true">404</p>

    <p class="eyebrow">Seite nicht gefunden</p>

    <h1>Hier gibt es leider nichts.</h1>

    <p class="lead">
        Die Seite wurde verschoben, gelöscht – oder die Adresse enthält einen Tippfehler.
    </p>

    <p class="hint">
        Über die Startseite finden Sie schnell wieder zu Mitgliedern, Terminen und allem anderen.
    </p>

    <div class="actions">
        <a href="{{ url('/') }}" class="btn">Zur Startseite</a>
        <a href="javascript:history.back()" class="btn btn-secondary">Zurück</a>
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
