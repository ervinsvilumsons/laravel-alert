<?php

declare(strict_types=1);

namespace ErvinsVilumsons\LaravelAlert\Contracts;

interface FilesystemContract
{
    public function ensureDirectoryExists(): bool;

    /** @param resource $handle */
    public function truncate($handle, int $size): bool;

    public function isWritable(): bool;

    /** @param resource $handle */
    public function write($handle, string $contents): int|false;

    /** @param resource $handle */
    public function flush($handle): bool;

    /** @return resource|false */
    public function open(string $path, string $mode);

    /** @param resource $handle */
    public function lockExclusive($handle): bool;

    /** @param resource $handle */
    public function unlock($handle): bool;

    /** @param resource $handle */
    public function close($handle): void;

    public function delete(string $path): void;
}
