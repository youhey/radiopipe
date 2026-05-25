<?php

namespace App\Providers;

use App\News\NewsProvider;
use App\News\NewsProviderManager;
use App\Upstream\UpstreamProvider;
use App\Upstream\UpstreamProviderManager;
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
        $this->app->singleton(NewsProviderManager::class);
        $this->app->singleton(UpstreamProviderManager::class);
        $this->app->singleton(WeatherProviderManager::class);

        $this->app->bind(NewsProvider::class, function (): NewsProvider {
            return $this->app->make(NewsProviderManager::class)->driver();
        });

        $this->app->bind(UpstreamProvider::class, function (): UpstreamProvider {
            return $this->app->make(UpstreamProviderManager::class)->driver();
        });

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
