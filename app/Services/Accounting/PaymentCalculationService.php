<?php

namespace App\Services\Accounting;

use App\Enums\PaymentMethod;
use App\Models\Clinic;
use App\Models\DailyWorkRow;
use App\Models\Payment;
use App\Support\ClinicCurrencySupport;
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
     * Compute TOTAL collected in the clinic's base currency and normalized AED.
     *
     * Primary payment fields (cash, cheque, Tabby, card) are always in the clinic currency.
     * The legacy `usd_amount` column holds foreign USD cash for AED clinics, or foreign AED cash for USD clinics.
     *
     * @return array{paid_total: string, paid_total_aed: string, usd_to_aed_amount: string, currency: string}
     */
    public function calculateTotalCollected(
        Clinic $clinic,
        string $dhsAmount,
        string $usdAmount,
        string $visaAmount,
        ?string $usdExchangeRate = null,
        string $chequeAmount = '0.00',
        string $tabbyAmount = '0.00',
    ): array {
        $clinicCurrency = ClinicCurrencySupport::baseCurrency($clinic);
        $exchangeRate = $usdExchangeRate ?? $this->defaultUsdExchangeRate;

        if (ClinicCurrencySupport::usesLegacyPaymentLayout($clinic)) {
            $result = $this->calculateTotalCollectedAed(
                $dhsAmount,
                $usdAmount,
                $visaAmount,
                $exchangeRate,
                chequeAmount: $chequeAmount,
                tabbyAmount: $tabbyAmount,
            );

            return [
                'paid_total' => $result['paid_total_aed'],
                'paid_total_aed' => $result['paid_total_aed'],
                'usd_to_aed_amount' => $result['usd_to_aed_amount'],
                'currency' => 'AED',
            ];
        }

        $primaryTotal = MoneyCalculator::add($dhsAmount, $chequeAmount, $tabbyAmount, $visaAmount);
        $foreignInClinic = ClinicCurrencySupport::foreignCashInClinicCurrency(
            $usdAmount,
            $clinicCurrency,
            $exchangeRate,
        );
        $paidTotal = MoneyCalculator::add($primaryTotal, $foreignInClinic);
        $paidTotalAed = ClinicCurrencySupport::toStoredAedEquivalent($paidTotal, $clinicCurrency, $exchangeRate);

        return [
            'paid_total' => $paidTotal,
            'paid_total_aed' => $paidTotalAed,
            'usd_to_aed_amount' => ClinicCurrencySupport::toStoredAedEquivalent(
                $foreignInClinic,
                $clinicCurrency,
                $exchangeRate,
            ),
            'currency' => $clinicCurrency,
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

        $dailyWorkRow->loadMissing('clinic');
        $clinic = $dailyWorkRow->clinic;
        $clinicCurrency = ClinicCurrencySupport::baseCurrency($clinic);
        $foreignCurrency = ClinicCurrencySupport::foreignCashCurrency($clinicCurrency);

        if (ClinicCurrencySupport::usesLegacyPaymentLayout($clinic)) {
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
        } else {
            $paymentDefinitions = [
                [
                    'method' => PaymentMethod::Dhs,
                    'amount' => (string) $dailyWorkRow->dhs_amount,
                    'currency' => $clinicCurrency,
                    'exchange_rate' => MoneyCalculator::rateToAed($clinicCurrency, $exchangeRate),
                ],
                [
                    'method' => PaymentMethod::Cheque,
                    'amount' => (string) $dailyWorkRow->cheque_amount,
                    'currency' => $clinicCurrency,
                    'exchange_rate' => MoneyCalculator::rateToAed($clinicCurrency, $exchangeRate),
                ],
                [
                    'method' => PaymentMethod::Tabby,
                    'amount' => (string) $dailyWorkRow->tabby_amount,
                    'currency' => $clinicCurrency,
                    'exchange_rate' => MoneyCalculator::rateToAed($clinicCurrency, $exchangeRate),
                ],
                [
                    'method' => PaymentMethod::Usd,
                    'amount' => (string) $dailyWorkRow->usd_amount,
                    'currency' => $foreignCurrency ?? 'USD',
                    'exchange_rate' => MoneyCalculator::rateToAed($foreignCurrency ?? 'USD', $exchangeRate),
                ],
                [
                    'method' => PaymentMethod::Visa,
                    'amount' => (string) $dailyWorkRow->visa_amount,
                    'currency' => $clinicCurrency,
                    'exchange_rate' => MoneyCalculator::rateToAed($clinicCurrency, $exchangeRate),
                ],
            ];
        }

        foreach ($paymentDefinitions as $definition) {
            if (bccomp($definition['amount'], '0', 2) === 0) {
                continue;
            }

            $amountAed = MoneyCalculator::convertToAed(
                $definition['amount'],
                $definition['currency'],
                $definition['exchange_rate'],
            );

            Payment::query()->create([
                'clinic_id' => $dailyWorkRow->clinic_id,
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
