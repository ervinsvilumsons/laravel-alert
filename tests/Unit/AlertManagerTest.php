<?php

declare(strict_types=1);

namespace ErvinsVilumsons\LaravelAlert\Tests\Unit;

use ErvinsVilumsons\LaravelAlert\AlertManager;
use ErvinsVilumsons\LaravelAlert\Notifications\AlertNotification;
use ErvinsVilumsons\LaravelAlert\Tests\TestCase;
use Illuminate\Contracts\Notifications\Dispatcher;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Mockery;
use ReflectionClass;

final class AlertManagerTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_it_returns_when_disabled(): void
    {
        $this->expectNotToPerformAssertions();

        Config::set('alert-manager.enabled', false);
        Config::set('alert-manager.channels', [
            'mail' => ['test@example.com'],
        ]);

        Cache::shouldReceive('add')->never();

        AlertManager::send('disabled', 'Title', 'Message');
    }

    public function test_it_returns_when_there_are_no_channels(): void
    {
        $this->expectNotToPerformAssertions();

        Config::set('alert-manager.enabled', true);
        Config::set('alert-manager.channels', []);

        Cache::shouldReceive('add')->never();

        AlertManager::send('no-channels', 'Title', 'Message');
    }

    public function test_it_sends_notification_successfully(): void
    {
        $this->expectNotToPerformAssertions();

        Notification::fake();

        Config::set('alert-manager.enabled', true);
        Config::set('alert-manager.channels', [
            'mail' => ['test@example.com'],
        ]);
        Config::set('alert-manager.throttle', 60);
        Config::set('alert-manager.notification', AlertNotification::class);

        Cache::shouldReceive('add')
            ->once()
            ->andReturn(true);

        Cache::shouldReceive('forget')->never();

        AlertManager::send(
            'successful-send',
            'Title',
            'Message',
            ['foo' => 'bar'],
            'error',
        );
    }

    public function test_invalid_level_falls_back_to_error(): void
    {
        $this->expectNotToPerformAssertions();

        Notification::fake();

        Config::set('alert-manager.enabled', true);
        Config::set('alert-manager.channels', [
            'mail' => ['test@example.com'],
        ]);
        Config::set('alert-manager.notification', AlertNotification::class);

        Cache::shouldReceive('add')
            ->once()
            ->andReturn(true);

        AlertManager::send(
            'invalid-level',
            'Title',
            'Message',
            [],
            'invalid-level',
        );
    }

    public function test_all_supported_levels_are_accepted(): void
    {
        foreach ([
            'alert',
            'critical',
            'debug',
            'emergency',
            'error',
            'info',
            'notice',
            'warning',
        ] as $level) {
            $this->assertTrue($this->sendWithLevel($level));
        }
    }

    private function sendWithLevel(string $level): bool
    {
        Notification::fake();

        Config::set('alert-manager.enabled', true);
        Config::set('alert-manager.channels', [
            'mail' => ['test@example.com'],
        ]);
        Config::set('alert-manager.notification', AlertNotification::class);

        Cache::shouldReceive('add')
            ->once()
            ->andReturn(true);

        AlertManager::send(
            'level-'.$level,
            'Title',
            'Message',
            [],
            $level,
        );

        return true;
    }

    public function test_it_returns_when_throttled(): void
    {
        $this->expectNotToPerformAssertions();

        Config::set('alert-manager.enabled', true);
        Config::set('alert-manager.channels', [
            'mail' => ['test@example.com'],
        ]);

        Cache::shouldReceive('add')
            ->once()
            ->andReturn(false);

        AlertManager::send('throttled', 'Title', 'Message');
    }

    public function test_zero_throttle_disables_throttling(): void
    {
        $this->expectNotToPerformAssertions();

        Config::set('alert-manager.enabled', true);
        Config::set('alert-manager.channels', [
            'mail' => ['test@example.com'],
        ]);
        Config::set('alert-manager.throttle', 0);

        Cache::shouldReceive('add')->never();
        Cache::shouldReceive('forget')->never();

        AlertManager::send('zero-throttle', 'Title', 'Message');
    }

    public function test_negative_throttle_disables_throttling(): void
    {
        $this->expectNotToPerformAssertions();

        Config::set('alert-manager.enabled', true);
        Config::set('alert-manager.channels', [
            'mail' => ['test@example.com'],
        ]);
        Config::set('alert-manager.throttle', -1);

        Cache::shouldReceive('add')->never();
        Cache::shouldReceive('forget')->never();

        AlertManager::send('negative-throttle', 'Title', 'Message');
    }

    public function test_invalid_notification_class_is_rejected_and_throttle_is_released(): void
    {
        $this->expectNotToPerformAssertions();

        Config::set('alert-manager.enabled', true);
        Config::set('alert-manager.channels', [
            'mail' => ['test@example.com'],
        ]);
        Config::set('alert-manager.notification', \stdClass::class);
        Config::set('alert-manager.throttle', 60);

        Cache::shouldReceive('add')
            ->once()
            ->andReturn(true);

        Cache::shouldReceive('forget')
            ->once()
            ->andReturn(true);

        Log::shouldReceive('error')
            ->once()
            ->with(
                'alert-manager.notification must extend AlertNotification',
                Mockery::type('array'),
            );

        AlertManager::send(
            'invalid-notification',
            'Title',
            'Message',
        );
    }

    public function test_non_string_notification_config_is_rejected(): void
    {
        $this->expectNotToPerformAssertions();

        Config::set('alert-manager.enabled', true);
        Config::set('alert-manager.channels', [
            'mail' => ['test@example.com'],
        ]);
        Config::set('alert-manager.notification', ['invalid']);
        Config::set('alert-manager.throttle', 60);

        Cache::shouldReceive('add')
            ->once()
            ->andReturn(true);

        Cache::shouldReceive('forget')
            ->once()
            ->andReturn(true);

        Log::shouldReceive('error')
            ->once()
            ->with(
                'alert-manager.notification must extend AlertNotification',
                Mockery::type('array'),
            );

        AlertManager::send(
            'invalid-notification-array',
            'Title',
            'Message',
        );
    }

    public function test_non_json_serializable_context_is_replaced(): void
    {
        $resource = tmpfile();

        if ($resource === false) {
            self::fail('Unable to create temporary resource.');
        }

        try {
            $result = $this->invokePrivate(
                'jsonSafe',
                [[
                    'resource' => $resource,
                ]],
            );

            self::assertSame(
                [
                    'warning' => 'context dropped; not JSON-serializable',
                ],
                $result,
            );
        } finally {
            fclose($resource);
        }
    }

    public function test_mail_routes_are_processed(): void
    {
        $this->expectNotToPerformAssertions();

        Notification::fake();

        Config::set('alert-manager.enabled', true);
        Config::set('alert-manager.channels', [
            'mail' => [
                'test@example.com',
                'other@example.com',
            ],
        ]);
        Config::set('alert-manager.notification', AlertNotification::class);

        Cache::shouldReceive('add')
            ->once()
            ->andReturn(true);

        AlertManager::send(
            'mail-routes',
            'Title',
            'Message',
        );
    }

    public function test_non_mail_routes_are_processed(): void
    {
        $this->expectNotToPerformAssertions();

        Notification::fake();

        Config::set('alert-manager.enabled', true);
        Config::set('alert-manager.channels', [
            'slack' => [
                'route-one',
                'route-two',
            ],
        ]);
        Config::set('alert-manager.notification', AlertNotification::class);

        Cache::shouldReceive('add')
            ->once()
            ->andReturn(true);

        AlertManager::send(
            'slack-routes',
            'Title',
            'Message',
        );
    }

    public function test_duplicate_routes_are_removed(): void
    {
        $this->expectNotToPerformAssertions();

        Notification::fake();

        Config::set('alert-manager.enabled', true);
        Config::set('alert-manager.channels', [
            'mail' => [
                'test@example.com',
                'test@example.com',
            ],
        ]);
        Config::set('alert-manager.notification', AlertNotification::class);

        Cache::shouldReceive('add')
            ->once()
            ->andReturn(true);

        AlertManager::send(
            'duplicate-routes',
            'Title',
            'Message',
        );
    }

    public function test_non_string_routes_are_removed(): void
    {
        $this->expectNotToPerformAssertions();

        Notification::fake();

        Config::set('alert-manager.enabled', true);
        Config::set('alert-manager.channels', [
            'mail' => [
                'test@example.com',
                123,
                null,
                true,
            ],
        ]);
        Config::set('alert-manager.notification', AlertNotification::class);

        Cache::shouldReceive('add')
            ->once()
            ->andReturn(true);

        AlertManager::send(
            'non-string-routes',
            'Title',
            'Message',
        );
    }

    public function test_invalid_channel_entries_are_ignored(): void
    {
        $this->expectNotToPerformAssertions();

        Notification::fake();

        Config::set('alert-manager.enabled', true);
        Config::set('alert-manager.channels', [
            123 => ['invalid'],
            'not-array' => 'invalid',
            'empty' => [],
            'valid' => ['route'],
        ]);
        Config::set('alert-manager.notification', AlertNotification::class);

        Cache::shouldReceive('add')
            ->once()
            ->andReturn(true);

        AlertManager::send(
            'invalid-channel-config',
            'Title',
            'Message',
        );
    }

    public function test_cache_exception_uses_local_throttle(): void
    {
        $key = 'alert:cache-failure-'.uniqid('', true);

        Cache::shouldReceive('add')
            ->twice()
            ->andThrow(new \RuntimeException('cache unavailable'));

        $first = $this->invokePrivate(
            'acquireThrottle',
            [$key, 60],
        );

        $second = $this->invokePrivate(
            'acquireThrottle',
            [$key, 60],
        );

        self::assertTrue($first);
        self::assertFalse($second);
    }

    public function test_cache_exception_is_not_used_when_throttle_is_disabled(): void
    {
        Cache::shouldReceive('add')->never();

        $result = $this->invokePrivate(
            'acquireThrottle',
            ['alert:disabled-throttle', 0],
        );

        self::assertTrue($result);
    }

    public function test_cache_add_false_does_not_acquire_throttle(): void
    {
        Cache::shouldReceive('add')
            ->once()
            ->andReturn(false);

        $result = $this->invokePrivate(
            'acquireThrottle',
            ['alert:already-throttled', 60],
        );

        self::assertFalse($result);
    }

    public function test_cache_add_true_acquires_throttle(): void
    {
        Cache::shouldReceive('add')
            ->once()
            ->andReturn(true);

        $result = $this->invokePrivate(
            'acquireThrottle',
            ['alert:acquired', 60],
        );

        self::assertTrue($result);
    }

    public function test_release_throttle_returns_when_disabled(): void
    {
        $this->expectNotToPerformAssertions();

        Cache::shouldReceive('forget')->never();

        $this->invokePrivate(
            'releaseThrottle',
            ['alert:disabled-release', 0],
        );
    }

    public function test_release_throttle_forgets_cache_key(): void
    {
        $this->expectNotToPerformAssertions();

        Cache::shouldReceive('forget')
            ->once()
            ->with('alert:released')
            ->andReturn(true);

        $this->invokePrivate(
            'releaseThrottle',
            ['alert:released', 60],
        );
    }

    public function test_release_throttle_clears_local_throttle_when_cache_fails(): void
    {
        $key = 'alert:release-local-'.uniqid('', true);

        Cache::shouldReceive('add')
            ->twice()
            ->andThrow(new \RuntimeException('cache unavailable'));

        Cache::shouldReceive('forget')
            ->once()
            ->with($key)
            ->andThrow(new \RuntimeException('cache unavailable'));

        $first = $this->invokePrivate(
            'acquireThrottle',
            [$key, 60],
        );

        $this->invokePrivate(
            'releaseThrottle',
            [$key, 60],
        );

        $second = $this->invokePrivate(
            'acquireThrottle',
            [$key, 60],
        );

        self::assertTrue($first);
        self::assertTrue($second);
    }

    public function test_delivery_success_returns_true(): void
    {
        $notification = new class extends \Illuminate\Notifications\Notification
        {
            /**
             * @return array<int, string>
             */
            public function via(object $notifiable): array
            {
                return [];
            }
        };

        $result = $this->invokePrivate(
            'deliver',
            [
                'mail',
                'test@example.com',
                $notification,
                'success-key',
                'Title',
                'error',
            ],
        );

        self::assertTrue($result);
    }

    public function test_delivery_failure_falls_back_to_sync_dispatch(): void
    {
        $notification = new class extends \Illuminate\Notifications\Notification
        {
            private int $calls = 0;

            /**
             * @return array<int, string>
             */
            public function via(object $notifiable): array
            {
                $this->calls++;

                if ($this->calls === 1) {
                    throw new \RuntimeException('queue dispatch failed');
                }

                return [];
            }
        };

        Log::shouldReceive('warning')
            ->once();

        Log::shouldReceive('error')
            ->never();

        $dispatcher = Mockery::mock(Dispatcher::class);

        $dispatcher
            ->shouldReceive('sendNow')
            ->once()
            ->andReturnNull();

        app()->instance(Dispatcher::class, $dispatcher);

        $result = $this->invokePrivate(
            'deliver',
            [
                'mail',
                'test@example.com',
                $notification,
                'sync-key',
                'Title',
                'error',
            ],
        );

        self::assertTrue($result);
    }

    public function test_delivery_and_sync_failure_returns_false(): void
    {
        $notification = new class extends \Illuminate\Notifications\Notification
        {
            /**
             * @return array<int, string>
             */
            public function via(object $notifiable): array
            {
                throw new \RuntimeException('queue dispatch failed');
            }
        };

        Log::shouldReceive('warning')
            ->once();

        Log::shouldReceive('error')
            ->once();

        $dispatcher = Mockery::mock(Dispatcher::class);

        $dispatcher
            ->shouldReceive('sendNow')
            ->once()
            ->andThrow(new \RuntimeException('sync failed'));

        app()->instance(Dispatcher::class, $dispatcher);

        $result = $this->invokePrivate(
            'deliver',
            [
                'mail',
                'test@example.com',
                $notification,
                'failed-key',
                'Title',
                'error',
            ],
        );

        self::assertFalse($result);
    }

    public function test_json_safe_preserves_serializable_context(): void
    {
        $result = $this->invokePrivate(
            'jsonSafe',
            [[
                123 => 'integer-key',
                'string' => 'value',
                'nested' => [
                    'foo' => 'bar',
                ],
            ]],
        );

        self::assertSame(
            [
                '123' => 'integer-key',
                'string' => 'value',
                'nested' => [
                    'foo' => 'bar',
                ],
            ],
            $result,
        );
    }

    public function test_json_safe_replaces_unserializable_context(): void
    {
        $resource = tmpfile();

        if ($resource === false) {
            self::fail('Unable to create temporary resource.');
        }

        try {
            $result = $this->invokePrivate(
                'jsonSafe',
                [[
                    'resource' => $resource,
                ]],
            );

            self::assertSame(
                [
                    'warning' => 'context dropped; not JSON-serializable',
                ],
                $result,
            );
        } finally {
            fclose($resource);
        }
    }

    public function test_get_channels_filters_invalid_entries(): void
    {
        Config::set('alert-manager.channels', [
            123 => ['ignored'],
            'not-array' => 'ignored',
            'empty' => [],
            'mixed' => [
                'one',
                'one',
                123,
                null,
                true,
                'two',
            ],
            'valid' => [
                'three',
            ],
        ]);

        $result = $this->invokePrivate('getChannels');

        self::assertSame(
            [
                'mixed' => ['one', 'two'],
                'valid' => ['three'],
            ],
            $result,
        );
    }

    public function test_get_channels_returns_empty_for_empty_config(): void
    {
        Config::set('alert-manager.channels', []);

        $result = $this->invokePrivate('getChannels');

        self::assertSame([], $result);
    }

    public function test_route_values_returns_mail_routes_as_single_route(): void
    {
        $result = $this->invokePrivate(
            'routeValues',
            [
                'mail',
                ['one@example.com', 'two@example.com'],
            ],
        );

        self::assertSame(
            [
                ['one@example.com', 'two@example.com'],
            ],
            $result,
        );
    }

    public function test_route_values_returns_non_mail_routes_directly(): void
    {
        $result = $this->invokePrivate(
            'routeValues',
            [
                'slack',
                ['one', 'two'],
            ],
        );

        self::assertSame(
            ['one', 'two'],
            $result,
        );
    }

    /**
     * @param  array<int, mixed>  $arguments
     */
    private function invokePrivate(string $method, array $arguments = []): mixed
    {
        $reflection = new ReflectionClass(AlertManager::class);
        $reflectionMethod = $reflection->getMethod($method);
        $reflectionMethod->setAccessible(true);

        return $reflectionMethod->invokeArgs(null, $arguments);
    }
}
