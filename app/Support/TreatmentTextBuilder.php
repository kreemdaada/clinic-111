<?php

namespace App\Support;

/**
 * Builds canonical treatment_text strings from structured code + quantity lines.
 */
final class TreatmentTextBuilder
{
    /**
     * @param  array<int, array{code: string, quantity: int|string}>  $lines
     */
    public static function fromLines(array $lines): string
    {
        $parts = [];

        foreach ($lines as $line) {
            $code = strtoupper(trim((string) ($line['code'] ?? '')));
            $quantity = (int) ($line['quantity'] ?? 0);

            if ($code === '' || $quantity < 1) {
                continue;
            }

            $parts[] = "{$code} x {$quantity}";
        }

        return implode(' + ', $parts);
    }
}
