<?php

namespace App\Enums;

/**
 * Payment channel for a {@see \App\Models\Payment} row.
 *
 * DHS, cheque, Tabby, and VISA are stored in AED. USD is converted to AED for TOTAL.
 */
enum PaymentMethod: string
{
    /** Cash payment in UAE dirhams (AED). */
    case Dhs = 'dhs';

    /** Cheque payment in AED. */
    case Cheque = 'cheque';

    /** Tabby payment in AED. */
    case Tabby = 'tabby';

    /** Cash payment in US dollars; stored with converted AED amount. */
    case Usd = 'usd';

    /** Card payment recorded in AED. */
    case Visa = 'visa';
}
