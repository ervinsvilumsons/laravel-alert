<?php

declare(strict_types=1);

use ErvinsVilumsons\LaravelAlert\AlertManager;
use ErvinsVilumsons\LaravelAlert\Notifications\AlertNotification;
use Illuminate\Contracts\Notifications\Dispatcher;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification as NotificationFacade;

beforeEach(function (): void {
    AlertManager::resetState();

    Config::set('alert-manager.enabled', true);
    Config::set('alert-manager.channels', ['mail' => ['test@example.com']]);
    Config::set('alert-manager.notification', AlertNotification::class);
    Config::set('alert-manager.throttle.ttl', 60);
    Config::set('alert-manager.throttle.path', $this->throttleDir());
});

afterEach(function (): void {
    Mockery::close();
});

/**
 * @param  array<array-key, mixed>  $channels
 */
function configureAlertManager(
    array $channels = ['mail' => ['test@example.com']],
    mixed $notification = AlertNotification::class,
    int $throttle = 60,
): void {
    Config::set('alert-manager.channels', $channels);
    Config::set('alert-manager.notification', $notification);
    Config::set('alert-manager.throttle.ttl', $throttle);
}

function expectThrottle(bool $acquired = true): void
{
    Cache::shouldReceive('add')->once()->andReturn($acquired);
}

/**
 * @param  array<string, mixed>  $context
 */
function sendAlert(
    string $key = 'key',
    string $level = 'error',
    array $context = [],
): void {
    AlertManager::send($key, 'Title', 'Message', $context, $level);
}

/**
 * @param  array<int, mixed>  $arguments
 */
function invokePrivate(string $method, array $arguments = []): mixed
{
    $reflection = new ReflectionClass(AlertManager::class);
    $method = $reflection->getMethod($method);

    return $method->invokeArgs(null, $arguments);
}

/**
 * @param  array<int, string>  $channels
 */
function fakeVia(array $channels): Notification
{
    return new class($channels) extends Notification
    {
        /** @param array<int, string> $channels */
        public function __construct(private array $channels) {}

        /** @return array<int, string> */
        public function via(object $notifiable): array
        {
            return $this->channels;
        }
    };
}

// --------------------------------------------------------- send() gating

it('returns when disabled', function (): void {
    NotificationFacade::fake();
    Config::set('alert-manager.enabled', false);
    Cache::shouldReceive('add')->never();

    sendAlert('disabled');

    NotificationFacade::assertNothingSent();
});

it('returns when there are no channels', function (): void {
    NotificationFacade::fake();
    Config::set('alert-manager.channels', []);
    Cache::shouldReceive('add')->never();

    sendAlert('no-channels');

    NotificationFacade::assertNothingSent();
});

it('sends notification successfully', function (): void {
    NotificationFacade::fake();
    expectThrottle();
    Cache::shouldReceive('forget')->never();

    sendAlert('successful-send', context: ['foo' => 'bar']);

    NotificationFacade::assertSentOnDemand(AlertNotification::class);
});

it('falls back to error for invalid level', function (): void {
    NotificationFacade::fake();
    expectThrottle();

    sendAlert('invalid-level', level: 'invalid-level');

    NotificationFacade::assertSentOnDemand(AlertNotification::class);
});

it('accepts all supported levels', function (string $level): void {
    NotificationFacade::fake();
    expectThrottle();

    sendAlert('level-'.$level, level: $level);

    NotificationFacade::assertSentOnDemand(AlertNotification::class);
})->with(AlertManager::LEVELS);

it('returns when throttled', function (): void {
    NotificationFacade::fake();
    expectThrottle(false);

    sendAlert('throttled');

    NotificationFacade::assertNothingSent();
});

it('skips cache when throttle is disabled', function (int $throttle): void {
    NotificationFacade::fake();
    Config::set('alert-manager.throttle.ttl', $throttle);
    Cache::shouldReceive('add')->never();
    Cache::shouldReceive('forget')->never();

    sendAlert('throttle-'.$throttle);

    NotificationFacade::assertSentOnDemand(AlertNotification::class);
})->with([0, -1]);

// --------------------------------------------------- notification class

it('rejects invalid notification config and releases throttle', function (mixed $class): void {
    NotificationFacade::fake();
    configureAlertManager(notification: $class);
    expectThrottle();

    Cache::shouldReceive('forget')->once()->andReturn(true);

    Log::shouldReceive('error')
        ->once()
        ->with(
            'alert-manager.notification must extend AlertNotification',
            Mockery::type('array'),
        );

    sendAlert('invalid-notification');

    NotificationFacade::assertNothingSent();
})->with([
    'stdClass' => [stdClass::class],
    'array' => [['invalid']],
]);

// ------------------------------------------------------- context safety

it('replaces non-json-serializable context', function (): void {
    NotificationFacade::fake();
    expectThrottle();

    $resource = tmpfile();
    if ($resource === false) {
        throw new RuntimeException('Unable to create temporary resource.');
    }

    try {
        sendAlert('bad-context', context: ['resource' => $resource]);

        NotificationFacade::assertSentOnDemand(AlertNotification::class);
    } finally {
        fclose($resource);
    }
});

// ---------------------------------------------------- channel handling

it('processes mail routes', function (): void {
    NotificationFacade::fake();
    expectThrottle();

    configureAlertManager(['mail' => ['test@example.com', 'other@example.com']]);
    sendAlert('mail-routes');

    NotificationFacade::assertSentOnDemand(AlertNotification::class);
});

it('processes non-mail routes', function (): void {
    NotificationFacade::fake();
    expectThrottle();

    configureAlertManager(['slack' => ['route-one', 'route-two']]);
    sendAlert('slack-routes');

    NotificationFacade::assertSentOnDemand(AlertNotification::class);
});

it('removes duplicate routes', function (): void {
    NotificationFacade::fake();
    expectThrottle();

    configureAlertManager(['mail' => ['test@example.com', 'test@example.com']]);
    sendAlert('duplicate-routes');

    NotificationFacade::assertSentOnDemand(AlertNotification::class);
});

it('removes non-string routes', function (): void {
    NotificationFacade::fake();
    expectThrottle();

    configureAlertManager(['mail' => ['test@example.com', 123, null, true]]);
    sendAlert('non-string-routes');

    NotificationFacade::assertSentOnDemand(AlertNotification::class);
});

it('ignores invalid channel entries', function (): void {
    NotificationFacade::fake();
    expectThrottle();

    configureAlertManager([
        123 => ['invalid'],
        'not-array' => 'invalid',
        'empty' => [],
        'valid' => ['route'],
    ]);

    sendAlert('invalid-channel-config');

    NotificationFacade::assertSentOnDemand(AlertNotification::class);
});

// -------------------------------------------------------- deliver() IO

it('returns true on delivery success', function (): void {
    $result = invokePrivate('deliver', [
        'mail',
        'test@example.com',
        fakeVia([]),
        'success-key',
        'Title',
        'error',
    ]);

    expect($result)->toBeTrue();
});

it('falls back to sync dispatch on delivery failure', function (): void {
    $notification = new class extends Notification
    {
        private int $calls = 0;

        /** @return array<int, string> */
        public function via(object $notifiable): array
        {
            if (++$this->calls === 1) {
                throw new RuntimeException('queue dispatch failed');
            }

            return [];
        }
    };

    Log::shouldReceive('warning')->once();
    Log::shouldReceive('error')->never();

    $dispatcher = Mockery::mock(Dispatcher::class);
    $dispatcher->shouldReceive('sendNow')->once()->andReturnNull();
    app()->instance(Dispatcher::class, $dispatcher);

    $result = invokePrivate('deliver', [
        'mail', 'test@example.com', $notification, 'sync-key', 'Title', 'error',
    ]);

    expect($result)->toBeTrue();
});

it('returns false when delivery and sync both fail', function (): void {
    $notification = new class extends Notification
    {
        /** @return array<int, string> */
        public function via(object $notifiable): array
        {
            throw new RuntimeException('queue dispatch failed');
        }
    };

    Log::shouldReceive('warning')->once();
    Log::shouldReceive('error')->once();

    $dispatcher = Mockery::mock(Dispatcher::class);
    $dispatcher->shouldReceive('sendNow')->once()
        ->andThrow(new RuntimeException('sync failed'));
    app()->instance(Dispatcher::class, $dispatcher);

    $result = invokePrivate('deliver', [
        'mail', 'test@example.com', $notification, 'failed-key', 'Title', 'error',
    ]);

    expect($result)->toBeFalse();
});

// --------------------------------------------------------- jsonSafe()

it('preserves serializable context in jsonSafe', function (): void {
    $result = invokePrivate('jsonSafe', [[
        123 => 'integer-key',
        'string' => 'value',
        'nested' => ['foo' => 'bar'],
    ]]);

    expect($result)->toBe([
        '123' => 'integer-key',
        'string' => 'value',
        'nested' => ['foo' => 'bar'],
    ]);
});

// ------------------------------------------------------- getChannels()

it('filters invalid entries from getChannels', function (): void {
    Config::set('alert-manager.channels', [
        123 => ['ignored'],
        'not-array' => 'ignored',
        'empty' => [],
        'mixed' => ['one', 'one', 123, null, true, 'two'],
        'valid' => ['three'],
    ]);

    expect(invokePrivate('getChannels'))->toBe([
        'mixed' => ['one', 'two'],
        'valid' => ['three'],
    ]);
});

it('returns empty array from getChannels for empty config', function (): void {
    Config::set('alert-manager.channels', []);

    expect(invokePrivate('getChannels'))->toBe([]);
});

// ------------------------------------------------------- routeValues()

it('returns mail routes as a single route', function (): void {
    expect(invokePrivate('routeValues', [
        'mail',
        ['one@example.com', 'two@example.com'],
    ]))->toBe([
        ['one@example.com', 'two@example.com'],
    ]);
});

it('returns non-mail routes directly', function (): void {
    expect(invokePrivate('routeValues', ['slack', ['one', 'two']]))
        ->toBe(['one', 'two']);
});
