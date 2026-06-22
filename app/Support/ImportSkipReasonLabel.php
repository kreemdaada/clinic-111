<?php

namespace App\Support;

/**
 * Human-readable English labels for Excel parser skip-reason codes (extraction log UI).
 */
final class ImportSkipReasonLabel
{
    /**
     * @param  string|null  $code  Machine reason code from the parser.
     */
    public static function for(?string $code): string
    {
        return match ($code) {
            'stale_section' => 'Stale section (previous month)',
            'cash_row' => 'Cash summary row',
            'grand_total_row' => 'Grand total row',
            'transfer_row' => 'Transfer row',
            'special_section' => 'Special section',
            'not_a_daily_subtotal' => 'Not a daily subtotal row',
            'unknown_doctor_label' => 'Unknown doctor label',
            default => $code !== null && $code !== '' ? $code : '—',
        };
    }
}
