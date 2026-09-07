<?php

declare(strict_types=1);

namespace ErvinsVilumsons\LaravelAlert;

use Illuminate\Support\ServiceProvider;

class AlertManagerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/alert-manager.php', 'alert-manager');

        $this->app->singleton(AlertManager::class, fn (): AlertManager => new AlertManager);
    }

    public function boot(): void
    {
        $this->publishes([__DIR__.'/../config/alert-manager.php' => config_path('alert-manager.php')], 'alert-manager-config');
    }
}
