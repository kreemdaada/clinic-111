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
}
