<?php

namespace App\Support;

/**
 * Maps Excel doctor labels/codes to canonical seeded doctor codes.
 *
 * Unknown labels (e.g. "Dr. Anas") are flagged for error display — not merged with JACK/RIYAD/etc.
 */
final class DoctorCodeResolver
{
    /** @var array<int, string> */
    public const KNOWN_CODES = ['JACK', 'PURIYA', 'RIYAD', 'WA'];

    /**
     * @return array{code: string, label: string, is_known: bool}
     */
    public static function resolve(?string $doctorCode, ?string $doctorLabel): array
    {
        $displayLabel = trim((string) ($doctorLabel ?: $doctorCode ?: 'Unknown'));

        foreach (self::candidateStrings($doctorCode, $doctorLabel) as $candidate) {
            $guess = DoctorLabelNormalizer::extractCodeGuess($candidate);

            if (in_array($guess, self::KNOWN_CODES, true)) {
                return [
                    'code' => $guess,
                    'label' => $displayLabel !== '' ? $displayLabel : $guess,
                    'is_known' => true,
                ];
            }
        }

        return [
            'code' => 'UNKNOWN',
            'label' => $displayLabel !== '' ? $displayLabel : 'Unknown',
            'is_known' => false,
        ];
    }

    /**
     * @return array<int, string>
     */
    private static function candidateStrings(?string $doctorCode, ?string $doctorLabel): array
    {
        $candidates = [];

        foreach ([$doctorCode, $doctorLabel] as $value) {
            $trimmed = trim((string) $value);

            if ($trimmed !== '' && ! in_array($trimmed, $candidates, true)) {
                $candidates[] = $trimmed;
            }
        }

        return $candidates;
    }
}
