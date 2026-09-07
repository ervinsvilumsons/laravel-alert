# Laravel Alert Manager

[![Latest Version on Packagist](https://img.shields.io/packagist/v/ervinsvilumsons/laravel-alert.svg?style=flat-square)](https://packagist.org/packages/ervinsvilumsons/laravel-alert)
![PHP 8.3+](https://img.shields.io/badge/PHP-8.3%2B-777BB4?logo=php)
![Laravel 10+](https://img.shields.io/badge/Laravel-10%2B-FF2D20?logo=laravel&logoColor=white)
[![Tests](https://github.com/ervinsvilumsons/laravel-alert/actions/workflows/ci.yml/badge.svg)](https://github.com/ervinsvilumsons/laravel-alert/actions/workflows/ci.yml)
[![codecov](https://codecov.io/github/ervinsvilumsons/laravel-alert/branch/staging/graph/badge.svg?token=0F2HQQXZH2)](https://codecov.io/github/ervinsvilumsons/laravel-alert)
[![License](https://img.shields.io/github/license/ervinsvilumsons/laravel-alert)](https://github.com/ervinsvilumsons/laravel-alert/blob/main/LICENSE)

Laravel Alert provides a small, configurable way to notify a group of recipients.

## 📦 Installation

```bash
composer require ervinsvilumsons/laravel-alert
```

Publish the configuration:

```bash
php artisan vendor:publish --tag=alert-manager-config
```

This creates `config/alert-manager.php`.

## 🚀 Quick Start

```php
use AlertManager;

AlertManager::send(
    title: 'Payment service unavailable',
    message: 'The payment provider did not respond.',
    context: [
        'order_id' => $order->id,
        'provider' => 'acme-payments',
    ],
    level: 'critical',
);
```

### Configuration

The default configuration is:

```php
return [
    'enabled' => env('ALERTS_ENABLED', true),
    'queue' => env('ALERTS_QUEUE', 'default'),
    'channels' => [
        'mail',
        // 'slack',
    ],
    'notifiables' => [],
    'notification' => AlertNotification::class,
];
```

### Custom notification

To add or replace notification channels, create a notification class with the same constructor data shape and configure it in `config/alert-manager.php`:

```php
'notification' => App\Notifications\CustomAlertNotification::class,
```

## ⚖️ License

Laravel Alert Manager is released under the [MIT License](LICENSE).
