<?php

namespace App\Providers;

use App\Contracts\Security\CaptchaVerifier;
use App\Services\Audit\AuditLogService;
use App\Services\Currency\CurrencyFormatter;
use App\Services\Security\FakeCaptchaVerifier;
use App\View\Composers\ClinicContextComposer;
use App\View\Composers\PublicContactComposer;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
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
        $this->app->singleton(CurrencyFormatter::class);

        $this->app->bind(CaptchaVerifier::class, function () {
            $driver = (string) config('auth_security.captcha.driver', 'fake');

            return match ($driver) {
                'fake' => $this->app->make(FakeCaptchaVerifier::class),
                default => $this->app->make(FakeCaptchaVerifier::class),
            };
        });
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
            )->by($request->ip())->response(function (Request $request, array $headers) {
                app(AuditLogService::class)->logRegistrationAbuse($request->ip());

                return response('Too Many Attempts.', 429, $headers);
            });
        });

        RateLimiter::for('forgot-password', function (Request $request) {
            $email = strtolower(trim((string) $request->input('email', '')));

            return Limit::perMinute(
                (int) config('auth_security.password_reset.max_attempts', 5)
            )->by($email.'|'.$request->ip());
        });

        View::composer([
            'layouts.app',
            'logs.*',
            'daily-reports.*',
            'imports.*',
            'configuration.dashboard',
            'clinic-financial-overview.*',
            'monthly-income.*',
            'lab-prices.*',
            'doctor-fixed-fees.*',
            'doctors.*',
            'labs.*',
            'treatments.*',
            'clinics.*',
        ], ClinicContextComposer::class);

        View::composer([
            'landing.*',
            'layouts.legal',
            'legal.*',
        ], PublicContactComposer::class);

        Paginator::defaultView('vendor.pagination.clinic');
        Paginator::defaultSimpleView('vendor.pagination.clinic');
    }
}
