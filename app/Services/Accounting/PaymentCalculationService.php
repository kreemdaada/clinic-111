<?php

namespace App\Services\Accounting;

use App\Enums\PaymentMethod;
use App\Models\DailyWorkRow;
use App\Models\Payment;
use App\Support\MoneyCalculator;
use Carbon\CarbonInterface;

/**
 * Calculates TOTAL (collected payments) from DHS, USD, and VISA amounts.
 *
 * TOTAL = DHS + (USD × exchange_rate) + VISA (all normalized to AED).
 */
class PaymentCalculationService
{
    public function __construct(
        private readonly string $defaultUsdExchangeRate = '3.65',
    ) {}

    public function calculateTotalCollectedAed(
        string $dhsAmount,
        string $usdAmount,
        string $visaAmount,
        ?string $usdExchangeRate = null,
        string $rublAmount = '0.00',
        ?string $rubToAedRate = null,
    ): array {
        $exchangeRate = $this->defaultUsdExchangeRate;
        if ($usdExchangeRate !== null) {
            $exchangeRate = $usdExchangeRate;
        }

        $rubRate = (string) config('accounting.rub_to_aed_rate', '0.0481');
        if ($rubToAedRate !== null) {
            $rubRate = $rubToAedRate;
        }

        $usdToAedAmount = MoneyCalculator::convertToAed($usdAmount, 'USD', $exchangeRate);
        $rublToAedAmount = bccomp($rublAmount, '0', 2) > 0
            ? bcmul($rublAmount, $rubRate, 2)
            : '0.00';

        $totalCollectedAed = MoneyCalculator::add(
            $dhsAmount,
            $usdToAedAmount,
            $rublToAedAmount,
            $visaAmount,
        );

        return [
            'usd_to_aed_amount' => $usdToAedAmount,
            'rubl_to_aed_amount' => $rublToAedAmount,
            'paid_total_aed' => $totalCollectedAed,
        ];
    }

    public function createPaymentsForWorkRow(
        DailyWorkRow $dailyWorkRow,
        ?CarbonInterface $paidAt = null,
        ?string $usdExchangeRate = null,
    ): void {
        if ($paidAt === null) {
            $paidAt = $dailyWorkRow->work_date;
        }

        $exchangeRate = $this->defaultUsdExchangeRate;
        if ($usdExchangeRate !== null) {
            $exchangeRate = $usdExchangeRate;
        }

        $paymentDefinitions = [
            [
                'method' => PaymentMethod::Dhs,
                'amount' => (string) $dailyWorkRow->dhs_amount,
                'currency' => 'AED',
                'exchange_rate' => '1',
            ],
            [
                'method' => PaymentMethod::Usd,
                'amount' => (string) $dailyWorkRow->usd_amount,
                'currency' => 'USD',
                'exchange_rate' => $exchangeRate,
            ],
            [
                'method' => PaymentMethod::Visa,
                'amount' => (string) $dailyWorkRow->visa_amount,
                'currency' => 'AED',
                'exchange_rate' => '1',
            ],
        ];

        foreach ($paymentDefinitions as $definition) {
            if (bccomp($definition['amount'], '0', 2) <= 0) {
                continue;
            }

            $amountAed = MoneyCalculator::convertToAed(
                $definition['amount'],
                $definition['currency'],
                $definition['exchange_rate'],
            );

            Payment::query()->create([
                'daily_work_row_id' => $dailyWorkRow->id,
                'payment_method' => $definition['method'],
                'amount' => $definition['amount'],
                'currency' => $definition['currency'],
                'exchange_rate' => $definition['exchange_rate'],
                'amount_aed' => $amountAed,
                'paid_at' => $paidAt,
            ]);
        }
    }
}
