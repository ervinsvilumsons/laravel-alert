<?php

namespace ErvinsVilumsons\LaravelAlert\Tests;

use ErvinsVilumsons\LaravelAlert\AlertManagerServiceProvider;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\Facades\File;
use Illuminate\Testing\PendingCommand;
use Orchestra\Testbench\TestCase as OrchestraTestCase;
use RuntimeException;

abstract class TestCase extends OrchestraTestCase
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
        $config->set('alert-manager.throttle.ttl', 3600);
        $config->set('alert-manager.throttle.path', $this->throttleDir());

        $config->set('cache.default', 'array');
        $config->set('queue.default', 'sync');
    }

    protected function throttleDir(): string
    {
        $token = getenv('TEST_TOKEN')
            ?: getenv('UNIQUE_TEST_TOKEN')
            ?: 'default';

        return storage_path(
            'framework/cache/alert-throttle/'.$token
        );
    }

    protected function clearThrottleDir(): void
    {
        $dir = $this->throttleDir();

        if (! is_dir($dir)) {
            return;
        }

        foreach (glob($dir.'/*') ?: [] as $file) {
            @unlink($file);
        }

        if (is_dir($dir)) {
            File::deleteDirectory($dir);
        }
    }

    /**
     * @return list<string>
     */
    protected function seedLockFiles(int $count): array
    {
        $dir = $this->throttleDir();

        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $paths = [];

        for ($i = 0; $i < $count; $i++) {
            $path = $dir.'/'.md5("key-{$i}").'.lock';
            file_put_contents($path, "key-{$i}\n".(microtime(true) + 60));
            $paths[] = $path;
        }

        return $paths;
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    protected function artisanTest(string $command, array $parameters = []): PendingCommand
    {
        $result = $this->artisan($command, $parameters);

        if (! $result instanceof PendingCommand) {
            throw new RuntimeException('Expected Laravel testing PendingCommand.');
        }

        return $result;
    }
}
