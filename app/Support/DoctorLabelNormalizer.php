<?php

namespace App\Support;

/**
 * Normalizes daily-report doctor section labels to canonical DB doctor codes.
 *
 * Handles spelling variants (PORIA → PURIYA, RIYADH → RIYAD) and strips "Dr." prefix.
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

    /**
     * Convert a raw Excel doctor label to the best-guess canonical code.
     *
     * Does not hit the database — use for matching hints and column mapping only.
     *
     * @param  string  $doctorLabel  Raw label from daily report (e.g. "Dr. Poria", "RIYADH").
     * @return string Uppercase canonical code or normalized label if no alias exists.
     */
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
     * Return all configured label → code alias mappings.
     *
     * @return array<string, string>
     */
    public static function aliases(): array
    {
        return self::CODE_ALIASES;
    }
}
