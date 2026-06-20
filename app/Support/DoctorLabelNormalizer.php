<?php

namespace App\Support;

/**
 * Normalizes daily-report doctor section labels to canonical DB doctor codes.
 */
class DoctorLabelNormalizer
{
    /** @var array<string, string> Parsed label fragment → canonical doctor code. */
    private const CODE_ALIASES = [
        'RIYADH' => 'RIYAD',
        'POURIA' => 'PURIYA',
        'PURIA' => 'PURIYA',
        'PORIA' => 'PURIYA',
        'POURIYA' => 'PURIYA',
        'WAEL' => 'WA',
    ];

    public static function extractCodeGuess(string $doctorLabel): string
    {
        $normalized = strtoupper(trim($doctorLabel));
        $normalized = preg_replace('/^DR\.?\s*/', '', $normalized) ?? $normalized;
        $normalized = trim($normalized);

        if (array_key_exists($normalized, self::CODE_ALIASES)) {
            return self::CODE_ALIASES[$normalized];
        }

        return $normalized;
    }

    /**
     * @return array<string, string>
     */
    public static function aliases(): array
    {
        return self::CODE_ALIASES;
    }
}
