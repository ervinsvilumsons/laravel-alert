<?php

declare(strict_types=1);

namespace ErvinsVilumsons\LaravelAlert\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

final class Throttle
{
    private const float FAILURE_COOLDOWN = 30.0; // seconds to skip a broken backend

    /** @var array<string, int> unix expiry — used only when cache is down */
    private static array $localThrottle = [];

    /** @var float unix ts — cache backend is skipped until this time */
    private static float $cacheDisabledUntil = 0.0;

    /** @var float unix ts — filesystem throttle is skipped until this time */
    private static float $filesystemDisabledUntil = 0.0;

    public static function directory(): ?string
    {
        $filesystem = self::filesystem();

        if (! $filesystem->ensureDirectoryExists()) {
            return null;
        }

        return $filesystem->isWritable()
            ? $filesystem->directory
            : null;
    }

    /**
     * Try to acquire a throttle slot. Returns true if the caller may proceed.
     */
    public static function acquire(string $cacheKey, int $ttl): bool
    {
        if ($ttl <= 0) {
            return true;
        }

        // 1) Configured cache driver (Redis/Memcached/DB) if breaker is closed.
        if (microtime(true) >= self::$cacheDisabledUntil) {
            try {
                return Cache::add($cacheKey, true, $ttl);
            } catch (Throwable $e) {
                self::$cacheDisabledUntil = microtime(true) + self::FAILURE_COOLDOWN;

                Log::warning('Cache unavailable, falling back to filesystem throttle', [
                    'exception' => $e,
                ]);
            }
        }

        // 2) Filesystem fallback — survives across FPM workers.
        if (microtime(true) >= self::$filesystemDisabledUntil) {
            $result = self::filesystemAcquire($cacheKey, $ttl);

            if ($result !== null) {
                return $result;
            }

            self::$filesystemDisabledUntil = microtime(true) + self::FAILURE_COOLDOWN;

            Log::warning('Filesystem throttle unavailable, using in-process throttle');
        }

        // 3) Last resort: in-process throttle (per FPM worker).
        $now = time();

        if ((self::$localThrottle[$cacheKey] ?? 0) > $now) {
            return false;
        }

        self::$localThrottle[$cacheKey] = $now + $ttl;

        return true;
    }

    /**
     * Atomic check-and-set on disk using flock.
     *
     * @return bool|null true = acquired, false = throttled, null = filesystem failure
     */
    private static function filesystemAcquire(string $cacheKey, int $ttl): ?bool
    {
        $filesystem = self::filesystem();

        if (! $filesystem->ensureDirectoryExists()) {
            return null;
        }

        if (! $filesystem->isWritable()) {
            return null;
        }

        $dir = $filesystem->directory;

        $path = $dir.'/'.md5($cacheKey).'.lock';

        $handle = $filesystem->open($path, 'c+');

        if ($handle === false) {
            return null;
        }

        try {
            if (! $filesystem->lockExclusive($handle)) {
                return null;
            }

            $contents = stream_get_contents($handle);
            $now = microtime(true);

            if (is_string($contents) && $contents !== '') {
                // Payload format: "<cacheKey>\n<expiry float>"
                [$storedKey, $storedExpiry] = array_pad(
                    explode("\n", $contents, 2),
                    2,
                    ''
                );

                // Only throttle when the stored key matches AND the entry is still live.
                if ($storedKey === $cacheKey && (float) $storedExpiry > $now) {
                    return false;
                }
            }

            $filesystem->truncate($handle, 0);
            rewind($handle);
            $filesystem->write($handle, $cacheKey."\n".($now + $ttl));
            $filesystem->flush($handle);

            return true;
        } finally {
            $filesystem->unlock($handle);
            $filesystem->close($handle);
        }
    }

    /**
     * Release a previously-acquired slot so the next call can fire immediately.
     */
    public static function release(string $cacheKey, int $ttl): void
    {
        if ($ttl <= 0) {
            return;
        }

        if (microtime(true) >= self::$cacheDisabledUntil) {
            try {
                Cache::forget($cacheKey);
            } catch (Throwable) {
                self::$cacheDisabledUntil = microtime(true) + self::FAILURE_COOLDOWN;
            }
        }

        self::filesystemRelease($cacheKey);

        unset(self::$localThrottle[$cacheKey]);
    }

    private static function filesystemRelease(string $cacheKey): void
    {
        $dir = self::directory();

        if ($dir === null) {
            return;
        }

        self::filesystem()->delete(
            $dir.'/'.md5($cacheKey).'.lock'
        );
    }

    /**
     * Resolve the filesystem service through Laravel's container.
     */
    private static function filesystem(): Filesystem
    {
        /** @var Filesystem $filesystem */
        $filesystem = app(Filesystem::class);

        return $filesystem;
    }

    /**
     * Reset circuit breakers and in-process throttle.
     *
     * @internal for tests only — do not call from application code.
     */
    public static function reset(): void
    {
        self::$localThrottle = [];
        self::$cacheDisabledUntil = 0.0;
        self::$filesystemDisabledUntil = 0.0;
    }
}
