<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Betriebsmodus
    |--------------------------------------------------------------------------
    |
    | "single": eine Installation für genau einen Verein (z. B.
    |           vema.sg-angerberg.at) — verhält sich wie bisher, es gibt keine
    |           zentrale Datenbank und keine Plattform-Routen.
    | "multi":  Plattform für viele Vereine (vemat.at). Eine zentrale
    |           "Landlord"-Datenbank kennt die Vereine, jeder Verein hat eine
    |           eigene Datenbank und ist unter <slug>.<tenant_domain> erreichbar.
    |
    */

    'mode' => env('TENANCY_MODE', 'single'),

    /*
     * Plattform-App (Registrierung, „Verein finden“, Plattform-Admin) und die
     * Domain, unter der die Vereine als <slug>.<tenant_domain> laufen. Die
     * Website auf der Domain selbst (vemat.at) ist nicht Teil der App.
     */
    'platform_host' => env('TENANCY_PLATFORM_HOST', 'app.vemat.at'),

    'tenant_domain' => env('TENANCY_TENANT_DOMAIN', 'vemat.at'),

    'scheme' => env('TENANCY_SCHEME', 'https'),

    /*
     * Nur für lokale Tests (z. B. "php artisan serve" auf 8145): Port, der an
     * Vereins-URLs in Redirects und Mails angehängt wird.
     */
    'url_port' => env('TENANCY_URL_PORT'),

    /*
     * Öffentliche Website (Preise, AGB, Impressum) — eigenständig, nicht Teil der App.
     */
    'website_url' => env('TENANCY_WEBSITE_URL', 'https://vemat.at'),

    /*
     * Datenbanken neuer Vereine: <prefix><slug> (+ "_demo" für den Testmodus).
     * Der DB-Benutzer braucht CREATE-Rechte auf dieses Präfix. Bei SQLite
     * (Tests) liegen die Dateien in sqlite_directory.
     */
    'database_prefix' => env('TENANCY_DATABASE_PREFIX', 'vema_t_'),

    'sqlite_directory' => database_path('tenants'),

    /*
     * Testphase nach der Registrierung (alle Funktionen, ohne Zahlungsdaten)
     * und Gültigkeit des Bestätigungslinks.
     */
    'trial_days' => 30,

    'verification_hours' => 72,

    /*
     * Empfänger für Benachrichtigungen der Plattform (neue Registrierung,
     * fehlgeschlagene Einrichtung).
     */
    'platform_admin_email' => env('TENANCY_PLATFORM_ADMIN_EMAIL'),

    /*
     * Zentrale Datenbank (Vereine, Plattform) und die zur Laufzeit auf die
     * Datenbank des jeweiligen Vereins gesetzte Verbindung.
     */
    'landlord_connection' => 'landlord',

    'tenant_connection' => 'tenant',

    /*
     * Dateien je Verein: <storage_root>/<tenant-id>/{private,branding,demo}.
     */
    'storage_root' => storage_path('app/tenants'),

    /*
     * Subdomains, die kein Verein belegen darf.
     */
    'reserved_slugs' => ['www', 'admin', 'api', 'app', 'mail', 'vema', 'demo', 'test', 'static', 'assets'],

];
