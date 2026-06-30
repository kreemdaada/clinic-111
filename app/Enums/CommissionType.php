<?php

namespace App\Enums;

use App\Models\Doctor;
use App\Services\Accounting\MonthlyIncomeCalculationService;

/**
 * How {@see Doctor} income is calculated for a reporting period.
 *
 * Stored in `doctors.commission_type`. Drives
 * {@see MonthlyIncomeCalculationService}.
 */
enum CommissionType: string
{
    /** Income = NET_TOTAL × commission_percentage / 100 (Jack, Riyad, Puriya). */
    case Percentage = 'percentage';

    /** Income = SUM(fixed_fee × quantity) from `doctor_fixed_fees` (Dr Wa). */
    case Fixed = 'fixed';
}
