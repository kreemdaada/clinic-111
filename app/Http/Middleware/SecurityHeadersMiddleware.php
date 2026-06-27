<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Production-safe HTTP security headers (ADR-033).
 */
class SecurityHeadersMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        if (! config('security.headers.enabled', false)) {
            return $response;
        }

        $response->headers->set('X-Frame-Options', (string) config('security.headers.frame_options', 'SAMEORIGIN'));
        $response->headers->set('X-Content-Type-Options', (string) config('security.headers.content_type_options', 'nosniff'));
        $response->headers->set('Referrer-Policy', (string) config('security.headers.referrer_policy', 'strict-origin-when-cross-origin'));

        $csp = config('security.headers.csp');
        if (is_string($csp) && $csp !== '') {
            $response->headers->set('Content-Security-Policy', $csp);
        }

        if (config('security.headers.hsts.enabled', false) && $request->isSecure()) {
            $maxAge = (int) config('security.headers.hsts.max_age', 31536000);
            $directives = "max-age={$maxAge}";

            if (config('security.headers.hsts.include_subdomains', true)) {
                $directives .= '; includeSubDomains';
            }

            $response->headers->set('Strict-Transport-Security', $directives);
        }

        return $response;
    }
}
