<?php

use ErvinsVilumsons\LaravelAlert\AlertManager;
use ErvinsVilumsons\LaravelAlert\Notifications\AlertNotification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
});

it('sends a notification when enabled', function () {
    Config::set('alert-manager.enabled', true);
    Config::set('alert-manager.channels', ['mail' => ['admin@example.com']]);

    AlertManager::send(
        key: 'test',
        title: 'Test Alert',
        message: 'Something happened',
        context: ['key' => 'value'],
        level: 'error',
    );

    Notification::assertSentOnDemand(AlertNotification::class);
});

it('does not send when disabled', function () {
    Config::set('alert-manager.enabled', false);
    Config::set('alert-manager.channels', ['mail' => ['admin@example.com']]);

    AlertManager::send('test', 'Test', 'Message');

    Notification::assertNothingSent();
});

it('does not send when no notifiables are configured', function () {
    Config::set('alert-manager.enabled', true);
    Config::set('alert-manager.channels', []);

    AlertManager::send('test', 'Test', 'Message');

    Notification::assertNothingSent();
});

it('does not send when the alert is throttled', function () {
    Config::set('alert-manager.enabled', true);
    Config::set('alert-manager.channels', ['mail' => ['admin@example.com']]);
    Cache::shouldReceive('add')->once()->andReturnFalse();

    AlertManager::send('test', 'Test', 'Message');

    Notification::assertNothingSent();
});

it('sends when the cache is unavailable', function () {
    Config::set('alert-manager.enabled', true);
    Config::set('alert-manager.channels', ['mail' => ['admin@example.com']]);
    Cache::shouldReceive('add')->once()->andThrow(new RuntimeException('Cache unavailable'));

    AlertManager::send('test', 'Test', 'Message');

    Notification::assertSentOnDemand(AlertNotification::class);
});

it('ignores malformed channel configuration', function () {
    Config::set('alert-manager.enabled', true);
    Config::set('alert-manager.channels', [
        0 => ['invalid@example.com'],
        'mail' => ['admin@example.com', 123, 'admin@example.com'],
    ]);

    AlertManager::send('test', 'Test', 'Message');

    Notification::assertSentOnDemand(AlertNotification::class);
});

it('passes correct data to the notification', function () {
    Config::set('alert-manager.enabled', true);
    Config::set('alert-manager.channels', ['mail' => ['admin@example.com']]);

    AlertManager::send(
        key: 'order_ORD-1',
        title: 'Payment Failed',
        message: 'Card declined',
        context: ['Order' => 'ORD-1'],
        level: 'critical',
    );

    Notification::assertSentOnDemand(AlertNotification::class, function (AlertNotification $notification) {
        return $notification->data = [
            'title' => 'Payment Failed',
            'message' => 'Card declined',
            'context' => ['Order' => 'ORD-1'],
            'level' => 'critical',
        ];
    });
});
