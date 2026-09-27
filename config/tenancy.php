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
    |           eigene Datenbank und ist unter <slug>.<central_domain> erreichbar.
    |
    */

    'mode' => env('TENANCY_MODE', 'single'),

    'central_domain' => env('TENANCY_CENTRAL_DOMAIN', 'vemat.at'),

    'scheme' => env('TENANCY_SCHEME', 'https'),

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
