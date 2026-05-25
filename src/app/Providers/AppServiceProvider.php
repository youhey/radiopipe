<?php

namespace App\Providers;

use App\Weather\WeatherProvider;
use App\Weather\WeatherProviderManager;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(WeatherProviderManager::class);

        $this->app->bind(WeatherProvider::class, function (): WeatherProvider {
            return $this->app->make(WeatherProviderManager::class)->driver();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
    }
}
