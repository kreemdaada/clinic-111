<?php

namespace Tests\Unit;

use App\Enums\PaymentMethod;
use App\Enums\ReportSourceType;
use App\Enums\ReportStatus;
use App\Models\Clinic;
use App\Models\DailyReport;
use App\Models\DailyWorkRow;
use App\Models\Doctor;
use App\Models\Payment;
use App\Services\Accounting\PaymentCalculationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Characterization tests for {@see PaymentCalculationService}.
 *
 * Expected totals and payment rows are computed with explicit bcmath in the test body.
 */
class PaymentCalculationServiceCharacterizationTest extends TestCase
{
    use RefreshDatabase;

    private const USD_TO_AED_RATE = '3.65';

    private PaymentCalculationService $paymentCalculationService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->paymentCalculationService = app(PaymentCalculationService::class);
    }

    public function test_legacy_clinic_111_creates_payment_row_for_each_non_zero_component(): void
    {
        $this->seedAccountingData();
        $clinic = $this->clinic111();
        $doctor = Doctor::query()->where('clinic_id', $clinic->id)->firstOrFail();
        $report = $this->createDailyReport([
            'report_date' => '2026-06-01',
            'source_type' => ReportSourceType::ManualEntry,
            'status' => ReportStatus::Uploaded,
        ]);
        $workRow = $this->createLegacyWorkRow($report, $doctor, [
            'dhs_amount' => '100.00',
            'cheque_amount' => '50.00',
            'tabby_amount' => '25.00',
            'usd_amount' => '10.00',
            'visa_amount' => '200.00',
        ]);

        $this->paymentCalculationService->createPaymentsForWorkRow($workRow);

        $payments = Payment::query()
            ->where('daily_work_row_id', $workRow->id)
            ->orderBy('payment_method')
            ->get()
            ->keyBy(fn (Payment $payment) => $payment->payment_method->value);

        $this->assertCount(5, $payments);
        $this->assertPaymentRow($payments['cheque'], $workRow, '50.00', 'AED', '1.0000', '50.00');
        $this->assertPaymentRow($payments['dhs'], $workRow, '100.00', 'AED', '1.0000', '100.00');
        $this->assertPaymentRow($payments['tabby'], $workRow, '25.00', 'AED', '1.0000', '25.00');
        $this->assertPaymentRow($payments['usd'], $workRow, '10.00', 'USD', '3.6500', '36.50');
        $this->assertPaymentRow($payments['visa'], $workRow, '200.00', 'AED', '1.0000', '200.00');
    }

    public function test_legacy_clinic_111_total_collected_matches_explicit_component_sum(): void
    {
        $dhs = '100.00';
        $cheque = '50.00';
        $tabby = '25.00';
        $usd = '10.00';
        $visa = '200.00';
        $usdToAed = bcmul($usd, self::USD_TO_AED_RATE, 2);
        $expectedTotal = bcadd(bcadd(bcadd(bcadd($dhs, $cheque, 2), $tabby, 2), $usdToAed, 2), $visa, 2);

        $result = $this->paymentCalculationService->calculateTotalCollected(
            $this->legacyClinic(),
            $dhs,
            $usd,
            $visa,
            usdExchangeRate: self::USD_TO_AED_RATE,
            chequeAmount: $cheque,
            tabbyAmount: $tabby,
        );

        $this->assertSame('411.50', $expectedTotal);
        $this->assertSame($expectedTotal, $result['paid_total']);
        $this->assertSame($expectedTotal, $result['paid_total_aed']);
        $this->assertSame('AED', $result['currency']);
    }

    public function test_calculate_total_collected_aed_includes_rub_conversion_when_present(): void
    {
        $rubl = '1000.00';
        $rubRate = (string) config('accounting.rub_to_aed_rate', '0.0481');
        $rublToAed = bcmul($rubl, $rubRate, 2);
        $expectedTotal = bcadd(bcadd('100.00', '200.00', 2), $rublToAed, 2);

        $result = $this->paymentCalculationService->calculateTotalCollectedAed(
            dhsAmount: '100.00',
            usdAmount: '0.00',
            visaAmount: '200.00',
            rublAmount: $rubl,
        );

        $this->assertSame('48.10', $rublToAed);
        $this->assertSame($expectedTotal, $result['paid_total_aed']);
    }

    public function test_non_legacy_aed_clinic_stores_original_amount_and_matching_aed_pivot(): void
    {
        $clinic = $this->createAedClinic();
        $report = DailyReport::query()->create([
            'clinic_id' => $clinic->id,
            'report_date' => '2026-07-01',
            'source_type' => ReportSourceType::ManualEntry,
            'status' => ReportStatus::Uploaded,
        ]);
        $doctor = Doctor::query()->create([
            'clinic_id' => $clinic->id,
            'name' => 'Dr AED',
            'code' => 'AED_DOC',
            'commission_type' => 'percentage',
            'commission_percentage' => 30,
            'is_active' => true,
        ]);
        $workRow = DailyWorkRow::query()->create([
            'clinic_id' => $clinic->id,
            'daily_report_id' => $report->id,
            'doctor_id' => $doctor->id,
            'work_date' => '2026-07-01',
            'dhs_amount' => '150.00',
            'cheque_amount' => '0.00',
            'tabby_amount' => '0.00',
            'usd_amount' => '0.00',
            'visa_amount' => '0.00',
            'paid_total_aed' => '150.00',
        ]);

        $this->paymentCalculationService->createPaymentsForWorkRow($workRow);

        $payment = Payment::query()->where('daily_work_row_id', $workRow->id)->sole();

        $this->assertSame(PaymentMethod::Dhs, $payment->payment_method);
        $this->assertSame('150.00', (string) $payment->amount);
        $this->assertSame('AED', $payment->currency);
        $this->assertSame('1.0000', (string) $payment->exchange_rate);
        $this->assertSame('150.00', (string) $payment->amount_aed);
        $this->assertSame($clinic->id, $payment->clinic_id);
        $this->assertSame($workRow->id, $payment->daily_work_row_id);
    }

    public function test_non_legacy_aed_clinic_total_includes_foreign_usd_cash_column(): void
    {
        $clinic = $this->createAedClinic();
        $primary = '100.00';
        $foreignUsd = '10.00';
        $foreignInAed = bcmul($foreignUsd, self::USD_TO_AED_RATE, 2);
        $expectedTotal = bcadd($primary, $foreignInAed, 2);

        $result = $this->paymentCalculationService->calculateTotalCollected(
            $clinic,
            dhsAmount: $primary,
            usdAmount: $foreignUsd,
            visaAmount: '0.00',
            usdExchangeRate: self::USD_TO_AED_RATE,
        );

        $this->assertSame('136.50', $expectedTotal);
        $this->assertSame($expectedTotal, $result['paid_total']);
        $this->assertSame($expectedTotal, $result['paid_total_aed']);
        $this->assertSame('AED', $result['currency']);
    }

    public function test_usd_clinic_preserves_original_usd_amount_and_stores_aed_equivalent(): void
    {
        $clinic = $this->createUsdClinic();
        $report = DailyReport::query()->create([
            'clinic_id' => $clinic->id,
            'report_date' => '2026-08-01',
            'source_type' => ReportSourceType::ManualEntry,
            'status' => ReportStatus::Uploaded,
        ]);
        $doctor = Doctor::query()->create([
            'clinic_id' => $clinic->id,
            'name' => 'Dr USD',
            'code' => 'USD_DOC',
            'commission_type' => 'percentage',
            'commission_percentage' => 30,
            'is_active' => true,
        ]);
        $workRow = DailyWorkRow::query()->create([
            'clinic_id' => $clinic->id,
            'daily_report_id' => $report->id,
            'doctor_id' => $doctor->id,
            'work_date' => '2026-08-01',
            'dhs_amount' => '100.00',
            'cheque_amount' => '0.00',
            'tabby_amount' => '0.00',
            'usd_amount' => '0.00',
            'visa_amount' => '0.00',
            'paid_total_aed' => '365.00',
        ]);

        $this->paymentCalculationService->createPaymentsForWorkRow($workRow);

        $payment = Payment::query()->where('daily_work_row_id', $workRow->id)->sole();

        $this->assertSame('100.00', (string) $payment->amount);
        $this->assertSame('USD', $payment->currency);
        $this->assertSame('3.6500', (string) $payment->exchange_rate);
        $this->assertSame('365.00', (string) $payment->amount_aed);
    }

    public function test_usd_clinic_total_aed_pivot_matches_explicit_conversion(): void
    {
        $clinic = $this->createUsdClinic();
        $primaryUsd = '100.00';
        $expectedAed = bcmul($primaryUsd, self::USD_TO_AED_RATE, 2);

        $result = $this->paymentCalculationService->calculateTotalCollected(
            $clinic,
            dhsAmount: $primaryUsd,
            usdAmount: '0.00',
            visaAmount: '0.00',
            usdExchangeRate: self::USD_TO_AED_RATE,
        );

        $this->assertSame($primaryUsd, $result['paid_total']);
        $this->assertSame('USD', $result['currency']);
        $this->assertSame($expectedAed, $result['paid_total_aed']);
        $this->assertSame('365.00', $expectedAed);
    }

    public function test_multiple_component_total_is_order_independent_for_identical_inputs(): void
    {
        $clinic = $this->legacyClinic();
        $dhs = '10.00';
        $cheque = '20.00';
        $tabby = '30.00';
        $usd = '5.00';
        $visa = '40.00';
        $usdToAed = bcmul($usd, self::USD_TO_AED_RATE, 2);
        $expected = bcadd(
            bcadd(bcadd(bcadd($dhs, $cheque, 2), $tabby, 2), $usdToAed, 2),
            $visa,
            2,
        );

        $first = $this->paymentCalculationService->calculateTotalCollected(
            $clinic,
            $dhs,
            $usd,
            $visa,
            usdExchangeRate: self::USD_TO_AED_RATE,
            chequeAmount: $cheque,
            tabbyAmount: $tabby,
        );

        $second = $this->paymentCalculationService->calculateTotalCollected(
            $clinic,
            $dhs,
            $usd,
            $visa,
            usdExchangeRate: self::USD_TO_AED_RATE,
            chequeAmount: $cheque,
            tabbyAmount: $tabby,
        );

        $this->assertSame('118.25', $expected);
        $this->assertSame($expected, $first['paid_total_aed']);
        $this->assertSame($first['paid_total_aed'], $second['paid_total_aed']);
    }

    public function test_zero_amount_components_do_not_create_payment_rows(): void
    {
        $this->seedAccountingData();
        $clinic = $this->clinic111();
        $doctor = Doctor::query()->where('clinic_id', $clinic->id)->firstOrFail();
        $report = $this->createDailyReport([
            'report_date' => '2026-06-02',
            'source_type' => ReportSourceType::ManualEntry,
            'status' => ReportStatus::Uploaded,
        ]);
        $workRow = $this->createLegacyWorkRow($report, $doctor, [
            'dhs_amount' => '0.00',
            'cheque_amount' => '0.00',
            'tabby_amount' => '0.00',
            'usd_amount' => '0.00',
            'visa_amount' => '75.00',
        ]);

        $this->paymentCalculationService->createPaymentsForWorkRow($workRow);

        $payments = Payment::query()->where('daily_work_row_id', $workRow->id)->get();

        $this->assertCount(1, $payments);
        $this->assertSame(PaymentMethod::Visa, $payments->first()->payment_method);
        $this->assertSame('75.00', (string) $payments->first()->amount);
    }

    public function test_repeated_create_payments_for_work_row_appends_duplicate_rows(): void
    {
        $this->seedAccountingData();
        $clinic = $this->clinic111();
        $doctor = Doctor::query()->where('clinic_id', $clinic->id)->firstOrFail();
        $report = $this->createDailyReport([
            'report_date' => '2026-06-03',
            'source_type' => ReportSourceType::ManualEntry,
            'status' => ReportStatus::Uploaded,
        ]);
        $workRow = $this->createLegacyWorkRow($report, $doctor, [
            'dhs_amount' => '20.00',
            'cheque_amount' => '0.00',
            'tabby_amount' => '0.00',
            'usd_amount' => '0.00',
            'visa_amount' => '0.00',
        ]);

        $this->paymentCalculationService->createPaymentsForWorkRow($workRow);
        $this->paymentCalculationService->createPaymentsForWorkRow($workRow->fresh());

        $payments = Payment::query()->where('daily_work_row_id', $workRow->id)->get();

        $this->assertCount(2, $payments);
        $this->assertTrue($payments->every(fn (Payment $payment) => $payment->payment_method === PaymentMethod::Dhs));
        $this->assertSame('20.00', (string) $payments[0]->amount);
        $this->assertSame('20.00', (string) $payments[1]->amount);
    }

    public function test_created_payments_inherit_work_row_clinic_id(): void
    {
        $this->seedAccountingData();
        $clinic = $this->clinic111();
        $doctor = Doctor::query()->where('clinic_id', $clinic->id)->firstOrFail();
        $report = $this->createDailyReport([
            'report_date' => '2026-06-04',
            'source_type' => ReportSourceType::ManualEntry,
            'status' => ReportStatus::Uploaded,
        ]);
        $workRow = $this->createLegacyWorkRow($report, $doctor, [
            'dhs_amount' => '15.00',
            'cheque_amount' => '0.00',
            'tabby_amount' => '0.00',
            'usd_amount' => '0.00',
            'visa_amount' => '0.00',
        ]);

        $this->paymentCalculationService->createPaymentsForWorkRow($workRow);

        $payment = Payment::query()->where('daily_work_row_id', $workRow->id)->sole();

        $this->assertSame($workRow->clinic_id, $payment->clinic_id);
        $this->assertSame($report->clinic_id, $payment->clinic_id);
        $this->assertSame($clinic->id, $payment->clinic_id);
    }

    public function test_usd_clinic_foreign_aed_cash_creates_separate_aed_payment_row(): void
    {
        $clinic = $this->createUsdClinic();
        $report = DailyReport::query()->create([
            'clinic_id' => $clinic->id,
            'report_date' => '2026-08-02',
            'source_type' => ReportSourceType::ManualEntry,
            'status' => ReportStatus::Uploaded,
        ]);
        $doctor = Doctor::query()->create([
            'clinic_id' => $clinic->id,
            'name' => 'Dr USD Foreign',
            'code' => 'USD_FOR',
            'commission_type' => 'percentage',
            'commission_percentage' => 30,
            'is_active' => true,
        ]);
        $workRow = DailyWorkRow::query()->create([
            'clinic_id' => $clinic->id,
            'daily_report_id' => $report->id,
            'doctor_id' => $doctor->id,
            'work_date' => '2026-08-02',
            'dhs_amount' => '0.00',
            'cheque_amount' => '0.00',
            'tabby_amount' => '0.00',
            'usd_amount' => '365.00',
            'visa_amount' => '0.00',
            'paid_total_aed' => '365.00',
        ]);

        $this->paymentCalculationService->createPaymentsForWorkRow($workRow);

        $payment = Payment::query()->where('daily_work_row_id', $workRow->id)->sole();

        $this->assertSame(PaymentMethod::Usd, $payment->payment_method);
        $this->assertSame('365.00', (string) $payment->amount);
        $this->assertSame('AED', $payment->currency);
        $this->assertSame('1.0000', (string) $payment->exchange_rate);
        $this->assertSame('365.00', (string) $payment->amount_aed);
    }

    private function legacyClinic(): Clinic
    {
        $this->seedAccountingData();

        return $this->clinic111();
    }

    private function createAedClinic(): Clinic
    {
        $clinic = Clinic::query()->create([
            'name' => 'Characterization AED Clinic',
            'code' => 'CHAR_AED',
            'currency' => 'AED',
            'timezone' => 'Asia/Dubai',
            'country' => 'UAE',
        ]);
        $clinic->is_active = true;
        $clinic->save();

        return $clinic;
    }

    private function createUsdClinic(): Clinic
    {
        $clinic = Clinic::query()->create([
            'name' => 'Characterization USD Clinic',
            'code' => 'CHAR_USD',
            'currency' => 'USD',
            'timezone' => 'America/New_York',
            'country' => 'USA',
        ]);
        $clinic->is_active = true;
        $clinic->save();

        return $clinic;
    }

    /**
     * @param  array<string, string>  $amounts
     */
    private function createLegacyWorkRow(DailyReport $report, Doctor $doctor, array $amounts): DailyWorkRow
    {
        return DailyWorkRow::query()->create([
            'clinic_id' => $report->clinic_id,
            'daily_report_id' => $report->id,
            'doctor_id' => $doctor->id,
            'work_date' => '2026-06-01',
            'dhs_amount' => $amounts['dhs_amount'],
            'cheque_amount' => $amounts['cheque_amount'],
            'tabby_amount' => $amounts['tabby_amount'],
            'usd_amount' => $amounts['usd_amount'],
            'visa_amount' => $amounts['visa_amount'],
            'paid_total_aed' => $amounts['paid_total_aed'] ?? '0.00',
        ]);
    }

    private function assertPaymentRow(
        Payment $payment,
        DailyWorkRow $workRow,
        string $amount,
        string $currency,
        string $exchangeRate,
        string $amountAed,
    ): void {
        $this->assertSame($workRow->clinic_id, $payment->clinic_id);
        $this->assertSame($workRow->id, $payment->daily_work_row_id);
        $this->assertSame($amount, (string) $payment->amount);
        $this->assertSame($currency, $payment->currency);
        $this->assertSame($exchangeRate, (string) $payment->exchange_rate);
        $this->assertSame($amountAed, (string) $payment->amount_aed);
        $this->assertSame($workRow->work_date?->toDateString(), $payment->paid_at?->toDateString());
    }
}
