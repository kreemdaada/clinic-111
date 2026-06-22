<?php

namespace App\Services\Import;

use App\Models\DailyWorkRow;
use App\Services\Accounting\TreatmentParserService;
use App\Support\IncomeSheetColumnMap;
use App\Support\LabCostTreatmentCatalog;
use App\Support\MoneyCalculator;

/**
 * Builds structured per-row diagnostics for import/parsing visibility.
 */
class ImportRowDiagnosticsBuilder
{
    /**
     * @param  TreatmentParserService  $treatmentParserService  Re-parses treatment text for diagnostics.
     */
    public function __construct(
        private readonly TreatmentParserService $treatmentParserService,
    ) {}

    /**
     * Build a structured diagnostics payload for one work row.
     *
     * Compares payments, parsed treatments, persisted work items, and export columns.
     *
     * @param  DailyWorkRow  $dailyWorkRow  Row with doctor and workItems loaded.
     * @return array<string, mixed> Diagnostics with payments, job, treatments, and issues.
     */
    public function buildForWorkRow(DailyWorkRow $dailyWorkRow): array
    {
        $dailyWorkRow->loadMissing(['doctor', 'workItems.treatment', 'workItems.labJob']);

        $doctorCode = $dailyWorkRow->doctor?->code;
        $expectedTotal = MoneyCalculator::add(
            MoneyCalculator::add(
                (string) $dailyWorkRow->dhs_amount,
                (string) $dailyWorkRow->usd_to_aed_amount,
            ),
            (string) $dailyWorkRow->visa_amount,
        );

        $payments = [
            'dhs_aed' => (string) $dailyWorkRow->dhs_amount,
            'usd' => (string) $dailyWorkRow->usd_amount,
            'usd_to_aed' => (string) $dailyWorkRow->usd_to_aed_amount,
            'visa_aed' => (string) $dailyWorkRow->visa_amount,
            'paid_total_aed' => (string) $dailyWorkRow->paid_total_aed,
            'expected_total_aed' => $expectedTotal,
            'payment_ok' => bccomp($expectedTotal, (string) $dailyWorkRow->paid_total_aed, 2) === 0,
        ];

        /** @var array<string, array<string, mixed>> $persistedByCode */
        $persistedByCode = [];
        $jobLines = [];
        $labTotal = '0.00';

        foreach ($dailyWorkRow->workItems as $workItem) {
            $code = $workItem->treatment->code;
            $unitCost = $workItem->labJob !== null ? (string) $workItem->labJob->unit_cost : '0.00';
            $lineTotal = $workItem->labJob !== null ? (string) $workItem->labJob->total_cost_aed : '0.00';

            $persistedByCode[$code] = [
                'quantity' => (int) $workItem->quantity,
                'confidence' => (int) $workItem->confidence,
                'unit_cost_aed' => $unitCost,
                'line_total_aed' => $lineTotal,
                'export_column' => IncomeSheetColumnMap::columnFor($doctorCode, $code),
            ];

            if ($workItem->labJob !== null) {
                $labTotal = MoneyCalculator::add($labTotal, $lineTotal);
                $jobLines[] = [
                    'code' => $code,
                    'quantity' => (int) $workItem->quantity,
                    'unit_cost_aed' => $unitCost,
                    'line_total_aed' => $lineTotal,
                    'export_column' => IncomeSheetColumnMap::columnFor($doctorCode, $code),
                    'confidence' => (int) $workItem->confidence,
                    'warning_message' => $workItem->warning_message,
                ];
            }
        }

        $treatments = [];
        $ignoredTreatments = [];
        $expectedJobFromQty = '0.00';

        foreach ($this->treatmentParserService->parse((string) ($dailyWorkRow->treatment_text ?? '')) as $parsedItem) {
            $code = $parsedItem->treatmentCode;
            $countsForJob = LabCostTreatmentCatalog::isLabCostCode($code);
            $exportColumn = IncomeSheetColumnMap::columnFor($doctorCode, $code);
            $persisted = $persistedByCode[$code] ?? null;

            $entry = [
                'code' => $code,
                'quantity' => $parsedItem->quantity,
                'confidence' => $parsedItem->confidence,
                'counts_for_job' => $countsForJob,
                'export_column' => $exportColumn,
                'persisted' => $persisted !== null,
                'unit_cost_aed' => $persisted['unit_cost_aed'] ?? '0.00',
                'line_total_aed' => $persisted['line_total_aed'] ?? '0.00',
                'warning_message' => $parsedItem->warningMessage,
            ];

            if ($countsForJob) {
                $treatments[] = $entry;

                if ($persisted !== null) {
                    $expectedJobFromQty = MoneyCalculator::add(
                        $expectedJobFromQty,
                        $persisted['line_total_aed'],
                    );
                }
            } else {
                $entry['status'] = 'ignored_no_lab_cost';
                $ignoredTreatments[] = $entry;
            }
        }

        $issues = $this->collectIssues(
            $dailyWorkRow,
            $payments,
            $treatments,
            $ignoredTreatments,
            $labTotal,
            $doctorCode,
        );

        return [
            'payments' => $payments,
            'treatments_lab' => $treatments,
            'treatments_ignored' => $ignoredTreatments,
            'job' => [
                'total_aed' => $labTotal,
                'lines' => $jobLines,
                'expected_from_lines_aed' => $expectedJobFromQty,
                'job_ok' => bccomp($labTotal, $expectedJobFromQty, 2) === 0,
                'payments_only_doctor' => IncomeSheetColumnMap::isPaymentsOnlyDoctor($doctorCode),
            ],
            'issues' => $issues,
            'issue_count' => count($issues),
        ];
    }

    /**
     * Collect payment, JOB, and parsing issues for a work row.
     *
     * @param  DailyWorkRow  $dailyWorkRow  Source work row.
     * @param  array<string, mixed>  $payments  Payment breakdown with payment_ok flag.
     * @param  array<int, array<string, mixed>>  $treatments  Lab-cost treatments from re-parse.
     * @param  array<int, array<string, mixed>>  $ignoredTreatments  Non-lab treatments from re-parse.
     * @param  string  $labTotal  Persisted JOB total in AED.
     * @param  string|null  $doctorCode  Doctor code for export-column checks.
     * @return array<int, array<string, mixed>> Issue entries with severity and message.
     */
    private function collectIssues(
        DailyWorkRow $dailyWorkRow,
        array $payments,
        array $treatments,
        array $ignoredTreatments,
        string $labTotal,
        ?string $doctorCode,
    ): array {
        $issues = [];

        if (! ($payments['payment_ok'] ?? true)) {
            $issues[] = [
                'severity' => 'error',
                'code' => 'payment_mismatch',
                'message' => sprintf(
                    'TOTAL %s ≠ DHS+USD+VISA %s',
                    $payments['paid_total_aed'],
                    $payments['expected_total_aed'],
                ),
            ];
        }

        if (IncomeSheetColumnMap::isPaymentsOnlyDoctor($doctorCode) && bccomp($labTotal, '0', 2) > 0) {
            $issues[] = [
                'severity' => 'warning',
                'code' => 'job_on_payments_only_doctor',
                'message' => "Dr {$doctorCode} is payments-only in Income Excel but JOB={$labTotal} AED was calculated.",
            ];
        }

        foreach ($treatments as $treatment) {
            if (($treatment['confidence'] ?? 100) < 80) {
                $issues[] = [
                    'severity' => 'warning',
                    'code' => 'low_confidence',
                    'message' => "{$treatment['code']}×{$treatment['quantity']}: ".($treatment['warning_message'] ?? 'low confidence'),
                ];
            }

            if ($treatment['counts_for_job'] && $treatment['export_column'] === null && ! IncomeSheetColumnMap::isPaymentsOnlyDoctor($doctorCode)) {
                $issues[] = [
                    'severity' => 'warning',
                    'code' => 'no_export_column',
                    'message' => "{$treatment['code']} has JOB cost but no Income Excel column (H–P) for this doctor.",
                ];
            }
        }

        if (bccomp($labTotal, '0', 2) > 0 && blank($dailyWorkRow->treatment_text)) {
            $issues[] = [
                'severity' => 'error',
                'code' => 'job_without_text',
                'message' => "JOB {$labTotal} AED but treatment text is empty.",
            ];
        }

        if (bccomp($labTotal, '0', 2) > 0 && bccomp((string) $dailyWorkRow->paid_total_aed, '0', 2) === 0) {
            $issues[] = [
                'severity' => 'warning',
                'code' => 'job_without_payment',
                'message' => "JOB {$labTotal} AED but TOTAL payment is 0.",
            ];
        }

        return $issues;
    }
}
