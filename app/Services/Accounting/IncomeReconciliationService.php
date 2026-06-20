<?php

namespace App\Services\Accounting;

use App\Models\DailyReport;
use App\Models\DailyWorkRow;
use App\Support\MoneyCalculator;
use Illuminate\Support\Collection;

/**
 * Validates that export-ready totals match persisted database calculations.
 */
class IncomeReconciliationService
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function validateReport(DailyReport $dailyReport): array
    {
        $dailyReport->load([
            'dailyWorkRows.doctor',
            'dailyWorkRows.workItems.treatment',
            'dailyWorkRows.workItems.labJob',
        ]);

        $issues = [];

        foreach ($dailyReport->dailyWorkRows as $dailyWorkRow) {
            $issues = array_merge($issues, $this->validateWorkRow($dailyWorkRow));
        }

        $issues = array_merge($issues, $this->validateDoctorAggregates($dailyReport->dailyWorkRows));

        return $issues;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function validateWorkRow(DailyWorkRow $dailyWorkRow): array
    {
        $issues = [];
        $expectedTotal = MoneyCalculator::add(
            MoneyCalculator::add(
                (string) $dailyWorkRow->dhs_amount,
                (string) $dailyWorkRow->usd_to_aed_amount,
            ),
            (string) $dailyWorkRow->visa_amount,
        );

        if (bccomp($expectedTotal, (string) $dailyWorkRow->paid_total_aed, 2) !== 0) {
            $issues[] = [
                'severity' => 'error',
                'type' => 'payment_mismatch',
                'doctor' => $dailyWorkRow->doctor?->code,
                'work_date' => $dailyWorkRow->work_date?->toDateString(),
                'message' => "paid_total_aed ({$dailyWorkRow->paid_total_aed}) does not match DHS+USD+VISA ({$expectedTotal}).",
            ];
        }

        $labTotal = '0.00';

        foreach ($dailyWorkRow->workItems as $workItem) {
            if ($workItem->labJob !== null) {
                $labTotal = MoneyCalculator::add($labTotal, (string) $workItem->labJob->total_cost_aed);
            }

            if ($workItem->confidence < 80) {
                $issues[] = [
                    'severity' => 'warning',
                    'type' => 'low_confidence_treatment',
                    'doctor' => $dailyWorkRow->doctor?->code,
                    'work_date' => $dailyWorkRow->work_date?->toDateString(),
                    'message' => "Low confidence for {$workItem->treatment->code} (qty {$workItem->quantity}): {$workItem->warning_message}",
                ];
            }
        }

        if (bccomp($labTotal, '0', 2) > 0 && blank($dailyWorkRow->treatment_text)) {
            $issues[] = [
                'severity' => 'error',
                'type' => 'lab_without_treatment_text',
                'doctor' => $dailyWorkRow->doctor?->code,
                'work_date' => $dailyWorkRow->work_date?->toDateString(),
                'message' => "Lab cost {$labTotal} AED without treatment_text.",
            ];
        }

        return $issues;
    }

    /**
     * @param  Collection<int, DailyWorkRow>  $workRows
     * @return array<int, array<string, mixed>>
     */
    private function validateDoctorAggregates(Collection $workRows): array
    {
        $issues = [];

        foreach ($workRows->groupBy('doctor_id') as $doctorRows) {
            /** @var DailyWorkRow|null $sampleRow */
            $sampleRow = $doctorRows->first();
            $doctorCode = $sampleRow?->doctor?->code ?? 'UNKNOWN';

            $paymentTotal = '0.00';
            $labTotal = '0.00';

            foreach ($doctorRows as $dailyWorkRow) {
                $paymentTotal = MoneyCalculator::add($paymentTotal, (string) $dailyWorkRow->paid_total_aed);

                foreach ($dailyWorkRow->workItems as $workItem) {
                    if ($workItem->labJob !== null) {
                        $labTotal = MoneyCalculator::add($labTotal, (string) $workItem->labJob->total_cost_aed);
                    }
                }
            }

            if (bccomp($paymentTotal, '0', 2) === 0 && bccomp($labTotal, '0', 2) > 0) {
                $issues[] = [
                    'severity' => 'error',
                    'type' => 'lab_without_payments',
                    'doctor' => $doctorCode,
                    'message' => "Doctor {$doctorCode} has lab cost {$labTotal} AED but zero collected payments.",
                ];
            }
        }

        return $issues;
    }

    /**
     * @param  array<int, array<string, mixed>>  $issues
     */
    public function hasErrors(array $issues): bool
    {
        foreach ($issues as $issue) {
            if (($issue['severity'] ?? '') === 'error') {
                return true;
            }
        }

        return false;
    }
}
