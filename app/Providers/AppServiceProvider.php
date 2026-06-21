<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

/**
 * Application-wide service provider for container bindings and bootstrapping.
 *
 * Currently empty; reserved for future singletons (e.g. parser config, exchange rate).
 */
class AppServiceProvider extends ServiceProvider
{
    /**
     * Register application services in the IoC container before boot.
     *
     * Called early in the request lifecycle. Use for bindings and singletons.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap application services after all providers are registered.
     *
     * Called once at startup. Use for view composers, observers, and config macros.
     */
    public function boot(): void
    {
        //
    }
}
