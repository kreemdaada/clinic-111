<?php

namespace App\Support;

/**
 * Canonical OPG treatment code matching (ADR-039).
 *
 * Treats OPG_NORMAL and OPG-NORMAL (and other punctuation variants) as the same code.
 */
final class OpgTreatmentCodes
{
    public const NORMAL = 'OPG_NORMAL';

    public const THREE_D = 'OPG_3D';

    public static function normalize(string $code): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $code) ?? $code);
    }

    public static function matches(string $code, string $canonical): bool
    {
        return self::normalize($code) === self::normalize($canonical);
    }

    public static function isNormal(string $code): bool
    {
        return self::matches($code, self::NORMAL);
    }

    public static function is3d(string $code): bool
    {
        return self::matches($code, self::THREE_D);
    }
}
