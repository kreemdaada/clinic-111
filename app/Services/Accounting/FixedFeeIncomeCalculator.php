<?php

namespace App\Services\Accounting;

use App\Models\DailyWorkRow;
use App\Models\DoctorFixedFee;
use App\Models\WorkItem;
use App\Support\MoneyCalculator;

/**
 * Fixed-fee doctor income lines — only IMPL, BG, and SINUS generate income for no-commission doctors.
 *
 * IMPL: always paid in AED (configured fee per unit).
 * BG / SINUS: USD fee × quantity — paid in USD when row has USD cash; otherwise converted to AED (fee × exchange rate).
 */
class FixedFeeIncomeCalculator
{
    /** @var array<int, string> */
    public const BILLABLE_CODES = ['IMPL', 'BG', 'SINUS'];

    public function __construct(
        private readonly string $defaultUsdExchangeRate = '3.65',
    ) {}

    /**
     * @return array{
     *     payout_amount: string,
     *     payout_currency: string,
     *     amount_aed: string,
     *     export_bucket: string|null
     * }|null Null when treatment is not a Wa fixed-fee code.
     */
    public function calculateLine(
        DailyWorkRow $dailyWorkRow,
        WorkItem $workItem,
        DoctorFixedFee $fixedFee,
        ?string $usdExchangeRate = null,
    ): ?array {
        $code = $workItem->treatment->code;

        if (! in_array($code, self::BILLABLE_CODES, true)) {
            return null;
        }

        $exchangeRate = $usdExchangeRate ?? $this->defaultUsdExchangeRate;
        $lineFee = MoneyCalculator::multiply((string) $fixedFee->fee_amount, $workItem->quantity);

        if ($code === 'IMPL') {
            return [
                'payout_amount' => $lineFee,
                'payout_currency' => 'AED',
                'amount_aed' => $lineFee,
                'export_bucket' => 'impl',
            ];
        }

        $amountAed = MoneyCalculator::convertToAed($lineFee, $fixedFee->currency, $exchangeRate);

        if ($this->rowPaidWithUsd($dailyWorkRow)) {
            return [
                'payout_amount' => $lineFee,
                'payout_currency' => $fixedFee->currency,
                'amount_aed' => $amountAed,
                'export_bucket' => $code === 'BG' ? 'bg' : 'sinus',
            ];
        }

        return [
            'payout_amount' => $amountAed,
            'payout_currency' => 'AED',
            'amount_aed' => $amountAed,
            'export_bucket' => null,
        ];
    }

    /**
     * Sum fixed-fee income in AED for all billable work items on a doctor's rows.
     *
     * @param  iterable<int, WorkItem>  $workItems
     * @param  array<int, DoctorFixedFee>  $fixedFeesByTreatmentId  treatment_id → fee
     */
    public function sumIncomeAed(
        iterable $workItems,
        array $fixedFeesByTreatmentId,
        ?string $usdExchangeRate = null,
    ): string {
        $total = '0.00';

        foreach ($workItems as $workItem) {
            $fixedFee = $fixedFeesByTreatmentId[$workItem->treatment_id] ?? null;

            if ($fixedFee === null) {
                continue;
            }

            $dailyWorkRow = $workItem->dailyWorkRow;

            if ($dailyWorkRow === null) {
                continue;
            }

            $line = $this->calculateLine($dailyWorkRow, $workItem, $fixedFee, $usdExchangeRate);

            if ($line === null) {
                continue;
            }

            $total = MoneyCalculator::add($total, $line['amount_aed']);
        }

        return $total;
    }

    private function rowPaidWithUsd(DailyWorkRow $dailyWorkRow): bool
    {
        return bccomp((string) $dailyWorkRow->usd_amount, '0', 2) > 0;
    }
}
