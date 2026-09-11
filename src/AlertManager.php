<?php

declare(strict_types=1);

namespace ErvinsVilumsons\LaravelAlert;

use ErvinsVilumsons\LaravelAlert\Contracts\AlertManagerContract;
use ErvinsVilumsons\LaravelAlert\Notifications\AlertNotification;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Throwable;

class AlertManager implements AlertManagerContract
{
    public static function send(
        string $key,
        string $title,
        string $message,
        array $context = [],
        string $level = 'error'
    ): void {
        if (! self::checkEnabled()) {
            return;
        }

        /** @var array<string, array<int, string>> $channels */
        $channels = self::getChannels();

        if (! self::checkChannels($channels)) {
            return;
        }

        if (! self::checkThrottle(self::generateCacheKey($key))) {
            return;
        }

        /** @var class-string $notificationClass */
        $notificationClass = Config::get(
            'alert-manager.notification',
            AlertNotification::class,
        );

        foreach ($channels as $channel => $routes) {
            $notifiables = new AnonymousNotifiable;
            $notifiables->route($channel, $routes);

            $notification = new $notificationClass([
                'title' => $title,
                'message' => $message,
                'context' => $context,
                'level' => $level,
            ]);

            try {
                if (self::shouldUseQueue()) {
                    try {
                        $notifiables->notify($notification);
                    } catch (Throwable) {
                        $notifiables->notifyNow($notification);
                    }
                } else {
                    $notifiables->notifyNow($notification);
                }
            } catch (Throwable $e) {
                Log::error('Failed to send alert', [
                    'channel' => $channel,
                    'exception' => $e,
                ]);
            }
        }
    }

    private static function checkEnabled(): bool
    {
        return Config::boolean('alert-manager.enabled', true);
    }

    /**
     * @param  array<string, array<int, string>>  $channels
     */
    private static function checkChannels(array $channels): bool
    {
        return ! empty($channels);
    }

    private static function checkThrottle(string $cacheKey): bool
    {
        if (Config::get('cache.default') === null) {
            return true;
        }

        try {
            return Cache::add(
                $cacheKey,
                true,
                Config::integer('alert-manager.throttle'),
            );
        } catch (Throwable) {
            // Cache unavailable — continue sending the alert.
            return true;
        }
    }

    private static function generateCacheKey(string $key): string
    {
        return 'alert-manager:'.md5($key);
    }

    /**
     * @return array<string, array<int, string>>
     */
    private static function getChannels(): array
    {
        $notifiables = Config::array('alert-manager.channels', []);
        $result = [];

        foreach ($notifiables as $channel => $values) {
            if (! is_string($channel) || ! is_array($values)) {
                continue;
            }

            $values = array_values(
                array_unique(
                    array_filter($values, is_string(...)),
                ),
            );

            if ($values !== []) {
                $result[$channel] = $values;
            }
        }

        return $result;
    }

    private static function shouldUseQueue(): bool
    {
        $default = Config::string('queue.default', 'sync');

        if ($default === 'sync') {
            return false;
        }

        try {
            Queue::connection($default)->size();

            return true;
        } catch (Throwable) {
            return false;
        }
    }
}
