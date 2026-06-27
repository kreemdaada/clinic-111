<?php

namespace App\Providers;

use App\View\Composers\ClinicContextComposer;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

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
        Password::defaults(function () {
            return Password::min(12)
                ->mixedCase()
                ->numbers()
                ->symbols();
        });

        RateLimiter::for('register-clinic', function (Request $request) {
            return Limit::perMinute(
                (int) config('auth_security.registration.max_attempts', 3)
            )->by($request->ip());
        });

        View::composer([
            'layouts.app',
            'logs.*',
            'daily-reports.*',
            'imports.*',
            'configuration.dashboard',
            'lab-prices.*',
            'doctor-fixed-fees.*',
            'doctors.*',
            'labs.*',
            'treatments.*',
            'clinics.*',
        ], ClinicContextComposer::class);
    }
}
