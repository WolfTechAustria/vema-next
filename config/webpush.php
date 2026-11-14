<?php

use NotificationChannels\WebPush\PushSubscription;

/*
 * Web-Push (PWA-Benachrichtigungen). Die VAPID-Schlüssel gelten für die
 * ganze Plattform (php artisan webpush:vapid); die Abos liegen in der
 * jeweiligen Vereinsdatenbank (Standardverbindung, daher keine eigene).
 */
return [

    'vapid' => [
        'subject' => env('VAPID_SUBJECT', env('APP_URL')),
        'public_key' => env('VAPID_PUBLIC_KEY'),
        'private_key' => env('VAPID_PRIVATE_KEY'),
        'pem_file' => env('VAPID_PEM_FILE'),
    ],

    'model' => PushSubscription::class,

    'table_name' => 'tb_push_subscriptions',

    'database_connection' => null,

    'client_options' => [],

    'automatic_padding' => env('WEBPUSH_AUTOMATIC_PADDING', true),

];
