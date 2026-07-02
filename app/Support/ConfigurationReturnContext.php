<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Allow-listed return context from the configuration dashboard to admin pages.
 */
class ConfigurationReturnContext
{
    public const QUERY_KEY = 'from';

    public const VALUE = 'configuration';

    public static function isActive(?string $from): bool
    {
        return $from === self::VALUE;
    }

    /**
     * @return array<string, string>
     */
    public static function query(): array
    {
        return [self::QUERY_KEY => self::VALUE];
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public static function merge(array $params): array
    {
        return array_merge($params, self::query());
    }

    public static function backUrl(): string
    {
        return route('configuration.dashboard');
    }

    public static function fromRequest(Request $request): ?string
    {
        $from = $request->input('return_from') ?? $request->input(self::QUERY_KEY) ?? $request->query(self::QUERY_KEY);

        return is_string($from) ? $from : null;
    }

    public static function isRequestActive(Request $request): bool
    {
        return self::isActive(self::fromRequest($request));
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public static function mergeFromRequest(Request $request, array $params = []): array
    {
        if (! self::isRequestActive($request)) {
            return $params;
        }

        return self::merge($params);
    }
}
