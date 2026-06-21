<?php

namespace App\Enums;

/**
 * Lifecycle status of a {@see \App\Models\LabJob} (calculated lab cost row).
 *
 * Financial records are never deleted; status changes preserve audit history.
 */
enum LabJobStatus: string
{
    /** Automatically computed from lab price × work-item quantity. */
    case Calculated = 'calculated';

    /** Manually corrected after initial calculation (future use). */
    case Adjusted = 'adjusted';

    /** Soft-cancelled; excluded from totals (future use). */
    case Cancelled = 'cancelled';
}
