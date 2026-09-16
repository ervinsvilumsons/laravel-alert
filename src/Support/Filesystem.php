<?php

declare(strict_types=1);

namespace ErvinsVilumsons\LaravelAlert\Support;

use ErvinsVilumsons\LaravelAlert\Contracts\FilesystemContract;
use Illuminate\Support\Facades\Config;

class Filesystem implements FilesystemContract
{
    public readonly string $directory;

    public function __construct()
    {
        $this->directory = Config::string('alert-manager.throttle.path');
    }

    public function ensureDirectoryExists(): bool
    {
        if (is_dir($this->directory)) {
            return true;
        }

        return @mkdir($this->directory, 0755, true)
            || is_dir($this->directory);
    }

    /**  @param resource $handle */
    /**  @param int<0, max> $size */
    public function truncate($handle, int $size): bool
    {
        return ftruncate($handle, $size);
    }

    public function isWritable(): bool
    {
        return is_writable($this->directory);
    }

    /**  @param resource $handle */
    public function write($handle, string $contents): int|false
    {
        return fwrite($handle, $contents);
    }

    /**  @param resource $handle */
    public function flush($handle): bool
    {
        return fflush($handle);
    }

    /** @return resource|false */
    public function open(string $path, string $mode)
    {
        return @fopen($path, $mode);
    }

    /**  @param resource $handle */
    public function lockExclusive($handle): bool
    {
        return flock($handle, LOCK_EX);
    }

    /** @param resource $handle */
    public function unlock($handle): bool
    {
        return flock($handle, LOCK_UN);
    }

    /** @param resource $handle */
    public function close($handle): void
    {
        fclose($handle);
    }

    public function delete(string $path): void
    {
        if (is_file($path)) {
            @unlink($path);
        }
    }
}
