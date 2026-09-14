<?php

declare(strict_types=1);

namespace ErvinsVilumsons\LaravelAlert;

use ErvinsVilumsons\LaravelAlert\Contracts\AlertManagerContract;
use ErvinsVilumsons\LaravelAlert\Notifications\AlertNotification;
use Illuminate\Contracts\Notifications\Dispatcher;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Throwable;

class AlertManager implements AlertManagerContract
{
    private const array LEVELS = [
        'alert',
        'critical',
        'debug',
        'emergency',
        'error',
        'info',
        'notice',
        'warning',
    ];

    /** @var array<string, int> unix expiry — used only when cache is down */
    private static array $localThrottle = [];

    public static function send(
        string $key,
        string $title,
        string $message,
        array $context = [],
        string $level = 'error'
    ): void {
        if (! Config::boolean('alert-manager.enabled', true)) {
            return;
        }

        $channels = self::getChannels();
        if ($channels === []) {
            return;
        }

        $level = in_array($level, self::LEVELS, true) ? $level : 'error';
        $context = self::jsonSafe($context);
        $cacheKey = 'alert:'.md5($key);
        $ttl = Config::integer('alert-manager.throttle', 60);

        if (! self::acquireThrottle($cacheKey, $ttl)) {
            return;
        }

        $notification = self::makeNotification($title, $message, $context, $level);
        if ($notification === null) {
            self::releaseThrottle($cacheKey, $ttl);

            return;
        }

        $sent = false;

        try {
            foreach ($channels as $channel => $routes) {
                foreach (self::routeValues($channel, $routes) as $route) {
                    if (self::deliver($channel, $route, $notification, $key, $title, $level)) {
                        $sent = true;
                    }
                }
            }
        } finally {
            if (! $sent) {
                self::releaseThrottle($cacheKey, $ttl);
            }
        }
    }

    /**
     * @param  array<int, string>  $routes
     * @return array<int, string|array<int, string>>
     */
    private static function routeValues(string $channel, array $routes): array
    {
        return $channel === 'mail' ? [$routes] : $routes;
    }

    private static function deliver(
        string $channel,
        mixed $route,
        Notification $notification,
        string $key,
        string $title,
        string $level
    ): bool {
        $notifiable = (new AnonymousNotifiable)->route($channel, $route);

        try {
            $notifiable->notify($notification);

            return true;
        } catch (Throwable $e) {
            Log::warning('Alert queue dispatch failed, sending sync', [
                'key' => $key,
                'channel' => $channel,
                'exception' => $e,
            ]);
        }

        try {
            app(Dispatcher::class)->sendNow($notifiable, $notification, [$channel]);

            return true;
        } catch (Throwable $e) {
            Log::error('Failed to send alert', [
                'key' => $key,
                'title' => $title,
                'level' => $level,
                'channel' => $channel,
                'exception' => $e,
            ]);

            return false;
        }
    }

    private static function acquireThrottle(string $cacheKey, int $ttl): bool
    {
        if ($ttl <= 0) {
            return true;
        }

        try {
            return Cache::add($cacheKey, true, $ttl);
        } catch (Throwable) {
            $now = time();
            if ((self::$localThrottle[$cacheKey] ?? 0) > $now) {
                return false;
            }
            self::$localThrottle[$cacheKey] = $now + $ttl;

            return true;
        }
    }

    private static function releaseThrottle(string $cacheKey, int $ttl): void
    {
        if ($ttl <= 0) {
            return;
        }

        try {
            Cache::forget($cacheKey);
        } catch (Throwable) {
            unset(self::$localThrottle[$cacheKey]);
        }
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private static function makeNotification(
        string $title,
        string $message,
        array $context,
        string $level
    ): ?Notification {
        $class = Config::get('alert-manager.notification', AlertNotification::class);

        if (! is_string($class) || ! is_a($class, AlertNotification::class, true)) {
            Log::error('alert-manager.notification must extend AlertNotification', [
                'class' => $class,
            ]);

            return null;
        }

        return new $class([
            'title' => $title,
            'message' => $message,
            'context' => $context,
            'level' => $level,
        ]);
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private static function jsonSafe(array $context): array
    {
        $encoded = json_encode($context);

        if ($encoded === false) {
            return ['warning' => 'context dropped; not JSON-serializable'];
        }

        $decoded = json_decode($encoded, true);

        // @codeCoverageIgnoreStart
        if (! is_array($decoded)) {
            return ['warning' => 'context dropped; not JSON-serializable'];
        }
        // @codeCoverageIgnoreEnd

        $result = [];

        foreach ($decoded as $key => $value) {
            $result[(string) $key] = $value;
        }

        return $result;
    }

    /** @return array<string, array<int, string>> */
    private static function getChannels(): array
    {
        $result = [];

        foreach (Config::array('alert-manager.channels', []) as $channel => $values) {
            if (! is_string($channel) || ! is_array($values)) {
                continue;
            }

            $values = array_values(array_unique(array_filter($values, is_string(...))));

            if ($values !== []) {
                $result[$channel] = $values;
            }
        }

        return $result;
    }
}
