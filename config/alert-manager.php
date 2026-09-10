<?php

use ErvinsVilumsons\LaravelAlert\Notifications\AlertNotification;

return [
    'enabled' => env('ALERTS_ENABLED', true),

    'queue' => env('ALERTS_QUEUE', 'default'),

    'channels' => [

        'mail' => [
            // ...
        ],

        'slack' => [
            // ...
        ],

    ],

    'throttle' => 3600,

    /*
    |--------------------------------------------------------------------------
    | Notification Class
    |--------------------------------------------------------------------------
    | You can replace this with your own notification class if you need
    | to add custom channels (Discord, Telegram, etc.).
    */
    'notification' => AlertNotification::class,
];
