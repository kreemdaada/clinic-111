<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sets the application locale from the authenticated user, session, or English fallback.
 */
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $this->resolveLocale($request);

        app()->setLocale($locale);

        View::share('textDirection', $this->textDirection($locale));

        return $next($request);
    }

    private function resolveLocale(Request $request): string
    {
        $supported = config('locales.supported', ['en']);
        $default = (string) config('locales.default', 'en');

        $userLocale = $request->user()?->locale;
        if (is_string($userLocale) && in_array($userLocale, $supported, true)) {
            return $userLocale;
        }

        $sessionLocale = $request->session()->get('locale');
        if (is_string($sessionLocale) && in_array($sessionLocale, $supported, true)) {
            return $sessionLocale;
        }

        return in_array($default, $supported, true) ? $default : 'en';
    }

    private function textDirection(string $locale): string
    {
        return in_array($locale, config('locales.rtl', []), true) ? 'rtl' : 'ltr';
    }
}
