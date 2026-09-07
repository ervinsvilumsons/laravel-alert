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
        $config->set('alert-manager.channels', ['mail']);
        $config->set('alert-manager.notifiables', ['admin@example.com']);
        $config->set('alert-manager.queues.only', []);
        $config->set('alert-manager.queues.except', []);
        $config->set('alert-manager.deduplication.enabled', false);
        $config->set('alert-manager.throttle.enabled', false);

        $config->set('cache.default', 'array');
        $config->set('queue.default', 'sync');
    }
}
