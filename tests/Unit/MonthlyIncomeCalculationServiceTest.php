<?php

namespace Tests\Unit;

use App\Services\Accounting\MonthlyIncomeCalculationService;
use Tests\TestCase;

class MonthlyIncomeCalculationServiceTest extends TestCase
{
    private MonthlyIncomeCalculationService $calculationService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->calculationService = app(MonthlyIncomeCalculationService::class);
    }

    public function test_percentage_doctor_income_calculation(): void
    {
        $totalCollectedAed = '43701.25';
        $labCostAed = '8110.00';
        $commissionPercentage = '35';

        $netTotalAed = $this->calculationService->calculateNetTotal($totalCollectedAed, $labCostAed);
        $doctorIncomeAed = $this->calculationService->calculatePercentageDoctorIncome(
            $netTotalAed,
            $commissionPercentage,
        );

        $this->assertSame('35591.25', $netTotalAed);
        $this->assertSame('12456.94', $doctorIncomeAed);
    }
}
