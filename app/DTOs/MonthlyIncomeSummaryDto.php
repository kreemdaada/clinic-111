<?php

namespace App\DTOs;

/**
 * Immutable monthly income summary for one doctor.
 *
 * All monetary values are decimal strings in AED (2 decimal places).
 * Produced by {@see \App\Services\Accounting\MonthlyIncomeCalculationService}.
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
     * @param  string  $clinicIncomeAed  NET_TOTAL − DOCTOR_INCOME.
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
        public array $treatmentCounts,
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
        return [
            'doctor_id' => $this->doctorId,
            'doctor_name' => $this->doctorName,
            'month' => $this->month,
            'total_dhs' => $this->totalDhs,
            'total_usd_to_aed' => $this->totalUsdToAed,
            'total_visa' => $this->totalVisa,
            'total_collected_aed' => $this->totalCollectedAed,
            'lab_cost_aed' => $this->labCostAed,
            'net_total_aed' => $this->netTotalAed,
            'doctor_income_aed' => $this->doctorIncomeAed,
            'clinic_income_aed' => $this->clinicIncomeAed,
            'treatment_counts' => $this->treatmentCounts,
        ];
    }
}
