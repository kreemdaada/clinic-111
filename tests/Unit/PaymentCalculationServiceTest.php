<?php

namespace Tests\Unit;

use App\Services\Accounting\PaymentCalculationService;
use Tests\TestCase;

class PaymentCalculationServiceTest extends TestCase
{
    private PaymentCalculationService $paymentCalculationService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->paymentCalculationService = app(PaymentCalculationService::class);
    }

    public function test_total_collected_aed_from_dhs_usd_and_visa(): void
    {
        $result = $this->paymentCalculationService->calculateTotalCollectedAed(
            dhsAmount: '1000.00',
            usdAmount: '500.00',
            visaAmount: '200.00',
            usdExchangeRate: '3.65',
        );

        $this->assertSame('1825.00', $result['usd_to_aed_amount']);
        $this->assertSame('3025.00', $result['paid_total_aed']);
    }

    public function test_total_includes_cheque_and_tabby_in_aed(): void
    {
        $result = $this->paymentCalculationService->calculateTotalCollectedAed(
            dhsAmount: '1000.00',
            usdAmount: '0.00',
            visaAmount: '200.00',
            chequeAmount: '300.00',
            tabbyAmount: '150.00',
        );

        $this->assertSame('1650.00', $result['paid_total_aed']);
    }

    public function test_usd_clinic_collects_primary_payments_in_usd(): void
    {
        $result = $this->paymentCalculationService->calculateTotalCollected(
            clinicCurrency: 'USD',
            dhsAmount: '1000.00',
            usdAmount: '0.00',
            visaAmount: '200.00',
            chequeAmount: '50.00',
            tabbyAmount: '25.00',
        );

        $this->assertSame('1275.00', $result['paid_total']);
        $this->assertSame('USD', $result['currency']);
        $this->assertSame('4653.75', $result['paid_total_aed']);
    }
}
