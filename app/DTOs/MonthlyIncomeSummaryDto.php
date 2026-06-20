<?php

namespace App\DTOs;

readonly class MonthlyIncomeSummaryDto
{
    /**
     * @param  array<string, int>  $treatmentCounts
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
