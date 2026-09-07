<?php

use ErvinsVilumsons\LaravelAlert\AlertManager;
use ErvinsVilumsons\LaravelAlert\Notifications\AlertNotification;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
});

it('sends a notification when enabled', function () {
    Config::set('alert-manager.enabled', true);
    Config::set('alert-manager.notifiables', ['admin@example.com']);

    AlertManager::send(
        title: 'Test Alert',
        message: 'Something happened',
        context: ['key' => 'value'],
        level: 'error'
    );

    Notification::assertSentOnDemand(AlertNotification::class);
});

it('does not send when disabled', function () {
    Config::set('alert-manager.enabled', false);
    Config::set('alert-manager.notifiables', ['admin@example.com']);

    AlertManager::send('Test', 'Message');

    Notification::assertNothingSent();
});

it('does not send when no notifiables are configured', function () {
    Config::set('alert-manager.enabled', true);
    Config::set('alert-manager.notifiables', []);

    AlertManager::send('Test', 'Message');

    Notification::assertNothingSent();
});

it('passes correct data to the notification', function () {
    Config::set('alert-manager.enabled', true);
    Config::set('alert-manager.notifiables', ['admin@example.com']);

    AlertManager::send(
        title: 'Payment Failed',
        message: 'Card declined',
        context: ['Order' => 'ORD-1'],
        level: 'critical'
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
