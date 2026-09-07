<?php

declare(strict_types=1);

namespace ErvinsVilumsons\LaravelAlert\Contracts;

interface AlertManagerContract
{
    /**
     * @param  array<string, mixed>  $context
     */
    public static function send(string $title, string $message, array $context = [], string $level = 'error'): void;
}
