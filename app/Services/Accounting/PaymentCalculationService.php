<?php

namespace App\Services\Accounting;

use App\Enums\PaymentMethod;
use App\Models\DailyWorkRow;
use App\Models\Payment;
use App\Support\MoneyCalculator;
use Carbon\CarbonInterface;

/**
 * Calculates TOTAL (collected payments) from DHS, cheque, Tabby, USD, and VISA amounts.
 *
 * TOTAL = DHS + cheque + Tabby + (USD × exchange_rate) + VISA (all normalized to AED).
 */
class PaymentCalculationService
{
    /**
     * @param  string  $defaultUsdExchangeRate  Default USD→AED rate when not overridden.
     */
    public function __construct(
        private readonly string $defaultUsdExchangeRate = '3.65',
    ) {}

    /**
     * Compute TOTAL collected in AED from payment components.
     *
     * @param  string  $dhsAmount  Cash AED amount.
     * @param  string  $usdAmount  Cash USD amount (converted separately).
     * @param  string  $visaAmount  Card payment in AED.
     * @param  string|null  $usdExchangeRate  Override for USD conversion.
     * @param  string  $rublAmount  Optional RUB amount (legacy column support).
     * @param  string|null  $rubToAedRate  Override for RUB conversion.
     * @param  string  $chequeAmount  Cheque payment in AED.
     * @param  string  $tabbyAmount  Tabby payment in AED.
     * @return array{usd_to_aed_amount: string, rubl_to_aed_amount: string, paid_total_aed: string}
     */
    public function calculateTotalCollectedAed(
        string $dhsAmount,
        string $usdAmount,
        string $visaAmount,
        ?string $usdExchangeRate = null,
        string $rublAmount = '0.00',
        ?string $rubToAedRate = null,
        string $chequeAmount = '0.00',
        string $tabbyAmount = '0.00',
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
            $chequeAmount,
            $tabbyAmount,
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

    /**
     * Create individual `payments` rows for non-zero DHS, USD, and VISA on a work row.
     *
     * Skips zero amounts. Sets `paid_at` to work date when not provided.
     *
     * @param  DailyWorkRow  $dailyWorkRow  Row with dhs/usd/visa amounts already set.
     * @param  CarbonInterface|null  $paidAt  Payment date (defaults to work_date).
     * @param  string|null  $usdExchangeRate  USD rate stored on the USD payment row.
     */
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
                'method' => PaymentMethod::Cheque,
                'amount' => (string) $dailyWorkRow->cheque_amount,
                'currency' => 'AED',
                'exchange_rate' => '1',
            ],
            [
                'method' => PaymentMethod::Tabby,
                'amount' => (string) $dailyWorkRow->tabby_amount,
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
