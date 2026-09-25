<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Testmodus
    |--------------------------------------------------------------------------
    |
    | Staff-Benutzer können nach dem normalen Login in den Testmodus wechseln.
    | Alle Datenbankzugriffe ihrer Sitzung laufen dann gegen eine Kopie der
    | Live-Datenbank, Mails gehen nur an sie selbst, Dateien landen in einem
    | eigenen Ordner.
    |
    */

    'live_connection' => env('DB_CONNECTION', 'sqlite'),

    'connection' => 'demo',

    /*
     * Tabellen, die beim Zurücksetzen nicht kopiert werden (Infrastruktur,
     * Tokens). Session, Cache und Queue bleiben immer auf der Live-DB.
     */
    'excluded_tables' => [
        'sessions',
        'cache',
        'cache_locks',
        'jobs',
        'job_batches',
        'failed_jobs',
        'password_reset_tokens',
        'tb_member_login_tokens',
    ],

    'storage_root' => storage_path('app/demo'),

    'reset_time' => env('DEMO_RESET_TIME', '03:00'),

];
