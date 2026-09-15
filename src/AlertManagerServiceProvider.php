<?php

declare(strict_types=1);

namespace ErvinsVilumsons\LaravelAlert;

use ErvinsVilumsons\LaravelAlert\Commands\PruneAlertThrottleCommand;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schedule;
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
        if ($this->app->runningInConsole()) {
            $this->commands([
                PruneAlertThrottleCommand::class,
            ]);

            if (Config::boolean('health-manager.schedule.prune_rate_limits', true)) {
                Schedule::command(PruneAlertThrottleCommand::class, ['--force'])
                    ->daily()
                    ->withoutOverlapping()
                    ->onOneServer();
            }
        }

        $this->publishes([__DIR__.'/../config/alert-manager.php' => config_path('alert-manager.php')], 'alert-manager');
    }
}
