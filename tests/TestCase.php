<?php

namespace ErvinsVilumsons\LaravelAlert\Tests;

use ErvinsVilumsons\LaravelAlert\AlertManagerServiceProvider;
use Illuminate\Contracts\Config\Repository;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            AlertManagerServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        /** @var Repository $config */
        $config = $app['config'];

        $config->set('alert-manager.enabled', true);
        $config->set('alert-manager.channels', ['mail' => ['admin@example.com']]);
        $config->set('alert-manager.throttle', 3600);

        $config->set('cache.default', 'array');
        $config->set('queue.default', 'sync');
    }
}
