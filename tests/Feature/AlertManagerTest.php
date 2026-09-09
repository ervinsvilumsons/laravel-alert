<?php

use ErvinsVilumsons\LaravelAlert\AlertManager;
use ErvinsVilumsons\LaravelAlert\Notifications\AlertNotification;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
});

it('can send an alert using the facade', function () {
    Config::set('alert-manager.enabled', true);
    Config::set('alert-manager.channels', ['mail' => ['admin@example.com']]);

    AlertManager::send(
        key: 'test',
        title: 'Test Alert',
        message: 'Something went wrong',
        context: ['foo' => 'bar'],
        level: 'error'
    );

    Notification::assertSentOnDemand(AlertNotification::class);
});

it('does not send an alert when disabled', function () {
    Config::set('alert-manager.enabled', false);
    Config::set('alert-manager.channels', ['mail' => ['admin@example.com']]);

    AlertManager::send('test', 'Test', 'Message');

    Notification::assertNothingSent();
});

it('uses the custom notification class from config', function () {
    Config::set('alert-manager.enabled', true);
    Config::set('alert-manager.channels', ['mail' => ['admin@example.com']]);
    Config::set('alert-manager.notification', AlertNotification::class);

    AlertManager::send('custom', 'Custom', 'Using custom notification');

    Notification::assertSentOnDemand(AlertNotification::class);
});
