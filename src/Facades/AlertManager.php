<?php

declare(strict_types=1);

namespace ErvinsVilumsons\LaravelAlert\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static void send(string $title, string $message, array<string, mixed> $context = [], string $level = 'error')
 *
 * @see AlertManager
 */
class AlertManager extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \ErvinsVilumsons\LaravelAlert\AlertManager::class;
    }
}
