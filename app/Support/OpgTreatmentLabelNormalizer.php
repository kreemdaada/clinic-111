<?php

namespace App\Support;

/**
 * Normalizes Excel OPG treatment labels into canonical treatment codes.
 */
final class OpgTreatmentLabelNormalizer
{
    /**
     * Bare `OPG` row that opens an OPG section (not a treatment activity line).
     */
    public static function isBareSectionMarker(?string $value): bool
    {
        return self::normalizeSpaces($value) === 'OPG';
    }

    /**
     * Whether column G contains an OPG treatment activity (including bare `OPG`).
     */
    public static function isOpgTreatmentLabel(?string $value): bool
    {
        if ($value === null || trim($value) === '') {
            return false;
        }

        $normalized = self::normalizeSpaces($value);

        return str_starts_with($normalized, 'OPG');
    }

    /**
     * @return array{code: string, quantity: int, nurse_alias: string|null}|null
     */
    public static function parse(?string $value): ?array
    {
        if (! self::isOpgTreatmentLabel($value)) {
            return null;
        }

        $normalized = self::normalizeSpaces($value);
        $code = self::detectCanonicalCode($normalized);

        if ($code === null) {
            return null;
        }

        return [
            'code' => $code,
            'quantity' => self::extractQuantity($value, $normalized),
            'nurse_alias' => self::extractNurseAlias($value, $normalized),
        ];
    }

    public static function treatmentText(string $canonicalCode, int $quantity): string
    {
        return trim($canonicalCode.' x '.$quantity);
    }

    private static function detectCanonicalCode(string $normalized): ?string
    {
        $compact = preg_replace('/[^A-Z0-9]/', '', $normalized) ?? $normalized;

        if (preg_match('/OPG.*3D/i', $normalized) === 1 || str_contains($compact, 'OPG3D')) {
            return OpgTreatmentCodes::THREE_D;
        }

        if (str_starts_with($normalized, 'OPG')) {
            return OpgTreatmentCodes::NORMAL;
        }

        return null;
    }

    private static function extractQuantity(string $raw, string $normalized): int
    {
        if (preg_match('/\bx\s*(\d+)\b/i', $raw, $matches) === 1) {
            $quantity = (int) $matches[1];

            return $quantity > 0 ? $quantity : 1;
        }

        if (preg_match('/\bx\s*(\d+)\b/i', $normalized, $matches) === 1) {
            $quantity = (int) $matches[1];

            return $quantity > 0 ? $quantity : 1;
        }

        return 1;
    }

    private static function extractNurseAlias(string $raw, string $normalized): ?string
    {
        if (preg_match('/^OPG\s*[-–]\s*(.+)$/iu', trim($raw), $matches) !== 1) {
            return null;
        }

        $suffix = trim($matches[1]);

        if ($suffix === '' || self::isIgnorableSuffix($suffix)) {
            return null;
        }

        if (preg_match('/\bx\s*\d+\b/i', $suffix) === 1 && ! preg_match('/[A-Za-z]/u', preg_replace('/\bx\s*\d+\b/i', '', $suffix) ?? '')) {
            return null;
        }

        return $suffix;
    }

    private static function isIgnorableSuffix(string $suffix): bool
    {
        $upper = strtoupper(trim($suffix));

        if ($upper === 'TRAST' || $upper === 'TRANS') {
            return true;
        }

        if (preg_match('/^3\s*D$/i', $suffix) === 1) {
            return true;
        }

        if (preg_match('/^X\s*\d+$/i', $suffix) === 1) {
            return true;
        }

        return self::detectCanonicalCode(self::normalizeSpaces($suffix)) !== null
            && ! str_contains(strtoupper($suffix), '-');
    }

    private static function normalizeSpaces(?string $value): string
    {
        if ($value === null) {
            return '';
        }

        return preg_replace('/\s+/u', ' ', strtoupper(trim($value))) ?? '';
    }
}
