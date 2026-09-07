<?php

declare(strict_types=1);

namespace ErvinsVilumsons\LaravelAlert;

use ErvinsVilumsons\LaravelAlert\Contracts\AlertManagerContract;
use ErvinsVilumsons\LaravelAlert\Notifications\AlertNotification;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Notification;

class AlertManager implements AlertManagerContract
{
    public static function send(
        string $title,
        string $message,
        array $context = [],
        string $level = 'error'
    ): void {
        if (! Config::boolean('alert-manager.enabled', true)) {
            return;
        }

        /** @var array<int, string> $notifiables */
        $notifiables = Config::array('alert-manager.notifiables', []);

        if (empty($notifiables)) {
            return;
        }

        /** @var class-string $notificationClass */
        $notificationClass = Config::get(
            'alert-manager.notification',
            AlertNotification::class,
        );

        Notification::route('mail', $notifiables)
            ->notify(new $notificationClass([
                'title' => $title,
                'message' => $message,
                'context' => $context,
                'level' => $level,
            ])
            );
    }
}
