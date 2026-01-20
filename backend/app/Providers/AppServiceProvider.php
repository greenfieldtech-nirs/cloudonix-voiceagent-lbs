<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton('distribution.strategy.factory', function ($app) {
            return new \App\Strategies\DistributionStrategyFactory();
        });

        $this->app->singleton(\App\Services\RedisStrategyService::class, function ($app) {
            return new \App\Services\RedisStrategyService();
        });

        $this->app->singleton(\App\Services\StrategyMonitor::class, function ($app) {
            return new \App\Services\StrategyMonitor($app->make(\App\Services\RedisStrategyService::class));
        });

        $this->app->singleton(\App\Services\CloudonixWebhookValidator::class, function ($app) {
            return new \App\Services\CloudonixWebhookValidator();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
