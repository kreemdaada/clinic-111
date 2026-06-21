<?php

namespace App\Enums;

/**
 * Payment channel for a {@see \App\Models\Payment} row.
 *
 * Each non-zero component (DHS, USD, VISA) is stored as a separate payment record.
 * USD amounts are converted to AED before summing into TOTAL.
 */
enum PaymentMethod: string
{
    /** Cash payment in UAE dirhams (AED). */
    case Dhs = 'dhs';

    /** Cash payment in US dollars; stored with converted AED amount. */
    case Usd = 'usd';

    /** Card payment recorded in AED. */
    case Visa = 'visa';
}
