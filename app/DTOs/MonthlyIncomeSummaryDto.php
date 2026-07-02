<?php

namespace App\DTOs;

use App\Services\Accounting\MonthlyIncomeCalculationService;
use App\Support\ClinicCurrencySupport;

/**
 * Immutable monthly income summary for one doctor.
 *
 * All monetary values are decimal strings in AED (2 decimal places).
 * Produced by {@see MonthlyIncomeCalculationService}.
 */
readonly class MonthlyIncomeSummaryDto
{
    /**
     * @param  int  $doctorId  Primary key of the doctor.
     * @param  string  $doctorName  Display name (e.g. "Dr Jack").
     * @param  string  $month  Calendar month label in `YYYY-MM` format.
     * @param  string  $totalDhs  Sum of DHS (cash AED) payments in the month.
     * @param  string  $totalUsdToAed  Sum of USD payments converted to AED.
     * @param  string  $totalVisa  Sum of VISA (card AED) payments in the month.
     * @param  string  $totalCollectedAed  TOTAL collected = DHS + USD→AED + VISA.
     * @param  string  $labCostAed  Sum of lab job costs (JOB) for the month.
     * @param  string  $netTotalAed  TOTAL − LAB_COST.
     * @param  string  $doctorIncomeAed  Amount owed to the doctor (commission rules).
     * @param  string  $clinicIncomeAed  NET_TOTAL − DOCTOR_INCOME − NURSE_COMMISSION.
     * @param  string  $nurseCommissionAed  Sum of nurse commission snapshots for the month.
     * @param  string  $opgNormalValueAed  Informative OPG_NORMAL treatment value (not revenue).
     * @param  string  $opg3dValueAed  Informative OPG_3D treatment value (not revenue).
     * @param  array<string, int>  $treatmentCounts  Work-item quantities grouped by treatment code.
     */
    public function __construct(
        public int $doctorId,
        public string $doctorName,
        public string $month,
        public string $totalDhs,
        public string $totalUsdToAed,
        public string $totalVisa,
        public string $totalCollectedAed,
        public string $labCostAed,
        public string $netTotalAed,
        public string $doctorIncomeAed,
        public string $clinicIncomeAed,
        public string $nurseCommissionAed,
        public string $opgNormalValueAed,
        public string $opg3dValueAed,
        public array $treatmentCounts,
        public string $currency = 'AED',
    ) {}

    /**
     * Serialize the summary to a snake_case array for JSON API responses.
     *
     * @return array{
     *     doctor_id: int,
     *     doctor_name: string,
     *     month: string,
     *     total_dhs: string,
     *     total_usd_to_aed: string,
     *     total_visa: string,
     *     total_collected_aed: string,
     *     lab_cost_aed: string,
     *     net_total_aed: string,
     *     doctor_income_aed: string,
     *     clinic_income_aed: string,
     *     treatment_counts: array<string, int>
     * }
     */
    public function toArray(): array
    {
        $convert = fn (string $amount): string => ClinicCurrencySupport::fromStoredAedEquivalent(
            $amount,
            $this->currency,
        );

        return [
            'doctor_id' => $this->doctorId,
            'doctor_name' => $this->doctorName,
            'month' => $this->month,
            'currency' => $this->currency,
            'total_dhs' => $this->totalDhs,
            'total_usd_to_aed' => $this->totalUsdToAed,
            'total_visa' => $this->totalVisa,
            'total_collected_aed' => $this->totalCollectedAed,
            'total_collected' => $convert($this->totalCollectedAed),
            'lab_cost_aed' => $this->labCostAed,
            'lab_cost' => $convert($this->labCostAed),
            'net_total_aed' => $this->netTotalAed,
            'net_total' => $convert($this->netTotalAed),
            'doctor_income_aed' => $this->doctorIncomeAed,
            'doctor_income' => $convert($this->doctorIncomeAed),
            'nurse_commission_aed' => $this->nurseCommissionAed,
            'nurse_commission' => $convert($this->nurseCommissionAed),
            'opg_normal_value_aed' => $this->opgNormalValueAed,
            'opg_normal_value' => $convert($this->opgNormalValueAed),
            'opg_3d_value_aed' => $this->opg3dValueAed,
            'opg_3d_value' => $convert($this->opg3dValueAed),
            'clinic_income_aed' => $this->clinicIncomeAed,
            'clinic_income' => $convert($this->clinicIncomeAed),
            'treatment_counts' => $this->treatmentCounts,
        ];
    }
}
