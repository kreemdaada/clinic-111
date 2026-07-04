<?php

namespace Tests\Feature\Accounting;

use App\Services\Accounting\MonthlyIncomeCalculationService;
use App\Services\Analytics\ClinicFinancialOverviewService;
use App\Services\Export\DoctorsIncomeExcelExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\Support\AccountingParityFixture;
use Tests\Support\BuildsAccountingParityFixture;
use Tests\TestCase;

class AccountingParityCharacterizationTest extends TestCase
{
    use BuildsAccountingParityFixture;
    use RefreshDatabase;

    private AccountingParityFixture $fixture;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccountingData();
        $this->seedClinic222Tenant();
        $this->authenticateAdmin();
        $this->fixture = $this->buildAccountingParityFixture();
    }

    public function test_characterizes_monthly_income_accounting_values(): void
    {
        $summary = app(MonthlyIncomeCalculationService::class)
            ->calculateForMonth(AccountingParityFixture::MONTH)
            ->firstWhere('doctorId', $this->fixture->doctor->id);

        $this->assertNotNull($summary, 'Monthly income summary for JACK must exist.');

        $this->assertSame(AccountingParityFixture::DHS_PAYMENT, $summary->totalDhs);
        $this->assertSame('0.00', $summary->totalUsdToAed);
        $this->assertSame('0.00', $summary->totalVisa);
        $this->assertSame(AccountingParityFixture::DHS_PAYMENT, $summary->totalCollectedAed);
        $this->assertSame(AccountingParityFixture::LAB_COST_AED, $summary->labCostAed);
        $this->assertSame(AccountingParityFixture::NET_TOTAL_AED, $summary->netTotalAed);
        $this->assertSame(AccountingParityFixture::DOCTOR_INCOME_AED, $summary->doctorIncomeAed);
        $this->assertSame(AccountingParityFixture::NURSE_COMMISSION_AED, $summary->nurseCommissionAed);
        $this->assertSame(AccountingParityFixture::CLINIC_INCOME_AED, $summary->clinicIncomeAed);
        $this->assertSame(AccountingParityFixture::OPG_NORMAL_VALUE_AED, $summary->opgNormalValueAed);
        $this->assertSame(AccountingParityFixture::OPG_3D_VALUE_AED, $summary->opg3dValueAed);
    }

    public function test_characterizes_practice_overview_accounting_values(): void
    {
        $overview = app(ClinicFinancialOverviewService::class)
            ->build(AccountingParityFixture::MONTH);

        $this->assertTrue($overview->hasData);

        $this->assertSame(AccountingParityFixture::DHS_PAYMENT, $overview->revenue->amount);
        $this->assertSame(AccountingParityFixture::LAB_COST_AED, $overview->labCost->amount);
        $this->assertSame(AccountingParityFixture::NURSE_COMMISSION_AED, $overview->nurseCommission->amount);
        $this->assertSame(AccountingParityFixture::OPG_NORMAL_VALUE_AED, $overview->opgNormalValue->amount);
        $this->assertSame(AccountingParityFixture::OPG_3D_VALUE_AED, $overview->opg3dValue->amount);

        // Practice Overview result = revenue - lab - nurse (doctor income is not subtracted).
        $this->assertSame(AccountingParityFixture::OVERVIEW_RESULT_AED, $overview->calculatedResult->amount);
    }

    public function test_characterizes_excel_export_accounting_values(): void
    {
        $path = app(DoctorsIncomeExcelExportService::class)->exportForReport($this->fixture->report->fresh());
        $spreadsheet = IOFactory::load($path);
        $sheet = $spreadsheet->getSheetByName('Dr.Jack');

        $this->assertNotNull($sheet, 'JACK export sheet must exist.');

        $dayRow = $this->fixture->exportDayRow;
        $totalRow = $this->fixture->exportTotalRow;

        $this->assertSame(
            (float) AccountingParityFixture::OPG_NORMAL_VALUE_AED,
            (float) $sheet->getCell('T'.$dayRow)->getCalculatedValue(),
            'Excel OPG-Normal day value must match monthly income characterization.',
        );
        $this->assertSame(
            (float) AccountingParityFixture::OPG_3D_VALUE_AED,
            (float) $sheet->getCell('U'.$dayRow)->getCalculatedValue(),
            'Excel OPG-3D day value must match monthly income characterization.',
        );
        $this->assertSame(
            (float) AccountingParityFixture::NURSE_COMMISSION_AED,
            (float) $sheet->getCell('V'.$dayRow)->getCalculatedValue(),
            'Excel nurse commission day value must match monthly income characterization.',
        );

        $this->assertSame(
            (float) AccountingParityFixture::OPG_NORMAL_VALUE_AED,
            (float) $sheet->getCell('T'.$totalRow)->getCalculatedValue(),
            'Excel OPG-Normal total row must match day totals for this fixture.',
        );
        $this->assertSame(
            (float) AccountingParityFixture::OPG_3D_VALUE_AED,
            (float) $sheet->getCell('U'.$totalRow)->getCalculatedValue(),
            'Excel OPG-3D total row must match day totals for this fixture.',
        );
        $this->assertSame(
            (float) AccountingParityFixture::NURSE_COMMISSION_AED,
            (float) $sheet->getCell('V'.$totalRow)->getCalculatedValue(),
            'Excel nurse commission total row must match day totals for this fixture.',
        );

        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);
    }

    public function test_keeps_comparable_accounting_values_consistent(): void
    {
        $monthly = app(MonthlyIncomeCalculationService::class)
            ->calculateForMonth(AccountingParityFixture::MONTH)
            ->firstWhere('doctorId', $this->fixture->doctor->id);

        $overview = app(ClinicFinancialOverviewService::class)
            ->build(AccountingParityFixture::MONTH);

        $this->assertNotNull($monthly);

        // Comparable clinic-wide totals for a single-doctor month fixture.
        $this->assertSame($monthly->totalCollectedAed, $overview->revenue->amount);
        $this->assertSame($monthly->labCostAed, $overview->labCost->amount);
        $this->assertSame($monthly->nurseCommissionAed, $overview->nurseCommission->amount);
        $this->assertSame($monthly->opgNormalValueAed, $overview->opgNormalValue->amount);
        $this->assertSame($monthly->opg3dValueAed, $overview->opg3dValue->amount);

        // Practice Overview result intentionally differs from monthly clinic income.
        $this->assertNotSame($monthly->clinicIncomeAed, $overview->calculatedResult->amount);
    }

    public function test_excludes_cross_clinic_accounting_data(): void
    {
        $this->buildClinic222AccountingIsolationNoise();

        $overview = app(ClinicFinancialOverviewService::class)
            ->build(AccountingParityFixture::MONTH);

        $monthly = app(MonthlyIncomeCalculationService::class)
            ->calculateForMonth(AccountingParityFixture::MONTH)
            ->firstWhere('doctorId', $this->fixture->doctor->id);

        $this->assertNotNull($monthly);
        $this->assertSame(AccountingParityFixture::DHS_PAYMENT, $overview->revenue->amount);
        $this->assertSame(AccountingParityFixture::NURSE_COMMISSION_AED, $overview->nurseCommission->amount);
        $this->assertSame(AccountingParityFixture::OPG_NORMAL_VALUE_AED, $overview->opgNormalValue->amount);
        $this->assertNotSame(AccountingParityFixture::CLINIC_B_REVENUE_AED, $overview->revenue->amount);
        $this->assertNotSame(AccountingParityFixture::CLINIC_B_NURSE_COMMISSION_AED, $overview->nurseCommission->amount);
        $this->assertNotSame(AccountingParityFixture::CLINIC_B_OPG_NORMAL_VALUE_AED, $overview->opgNormalValue->amount);

        $clinic222DoctorSummary = app(MonthlyIncomeCalculationService::class)
            ->calculateForMonth(AccountingParityFixture::MONTH)
            ->firstWhere('doctorName', 'Dr Clinic 222');

        $this->assertNull($clinic222DoctorSummary, 'Clinic 222 doctor must not appear in Clinic 111 monthly income.');
    }
}
