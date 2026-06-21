<?php

namespace App\Enums;

/**
 * How {@see \App\Models\Doctor} income is calculated for a reporting period.
 *
 * Stored in `doctors.commission_type`. Drives
 * {@see \App\Services\Accounting\MonthlyIncomeCalculationService}.
 */
enum CommissionType: string
{
    /** Income = NET_TOTAL × commission_percentage / 100 (Jack, Riyad, Puriya). */
    case Percentage = 'percentage';

    /** Income = SUM(fixed_fee × quantity) from `doctor_fixed_fees` (Dr Wa). */
    case Fixed = 'fixed';
}
