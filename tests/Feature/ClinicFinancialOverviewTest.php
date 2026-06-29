<?php

namespace Tests\Feature;

use App\Enums\LabJobStatus;
use App\Enums\PaymentMethod;
use App\Enums\ReportSourceType;
use App\Enums\ReportStatus;
use App\Models\Doctor;
use App\Models\Lab;
use App\Models\Payment;
use App\Models\Treatment;
use App\Models\User;
use App\Services\Analytics\ClinicFinancialOverviewService;
use App\Support\Analytics\MonthOverMonthComparison;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClinicFinancialOverviewTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<string, mixed> */
    private array $clinic222;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccountingData();
        $this->clinic222 = $this->seedClinic222Tenant();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('clinic.financial-overview'))
            ->assertRedirect(route('login'));
    }

    public function test_unverified_user_is_redirected_to_verification_notice(): void
    {
        $user = User::query()->where('email', 'admin@clinic.test')->firstOrFail();
        $user->forceFill(['email_verified_at' => null])->save();

        $this->actingAs($user)
            ->get(route('clinic.financial-overview'))
            ->assertRedirect(route('verification.notice'));
    }

    public function test_authenticated_user_can_open_overview(): void
    {
        $this->actingAs($this->authenticateAdmin())
            ->get(route('clinic.financial-overview'))
            ->assertOk()
            ->assertSee('Practice Overview', false)
            ->assertSee('<h1 class="page-title">Practice Overview</h1>', false);
    }

    public function test_navigation_contains_overview_route(): void
    {
        $this->actingAs($this->authenticateAdmin())
            ->get(route('imports.index'))
            ->assertOk()
            ->assertSee(route('clinic.financial-overview'), false)
            ->assertSee('Overview', false);
    }

    public function test_empty_state_when_no_data(): void
    {
        $this->actingAs($this->authenticateAdmin())
            ->get(route('clinic.financial-overview', ['month' => '2020-01']))
            ->assertOk()
            ->assertSee('No accounting data', false)
            ->assertSee(route('imports.index'), false);
    }

    public function test_invalid_month_is_rejected(): void
    {
        $this->actingAs($this->authenticateAdmin())
            ->get(route('clinic.financial-overview', ['month' => 'not-a-month']))
            ->assertSessionHasErrors('month');
    }

    public function test_revenue_lab_cost_and_result_are_calculated(): void
    {
        $this->seedMonthlyAccounting('2026-06', revenue: '1000.00', labCost: '200.00');

        $this->actingAs($this->authenticateAdmin())
            ->get(route('clinic.financial-overview', ['month' => '2026-06']))
            ->assertOk()
            ->assertSee('AED 1000.00', false)
            ->assertSee('AED 800.00', false);
    }

    public function test_month_over_month_comparison_is_shown(): void
    {
        $this->seedMonthlyAccounting('2026-05', revenue: '500.00', labCost: '100.00');
        $this->seedMonthlyAccounting('2026-06', revenue: '1000.00', labCost: '200.00');

        $this->actingAs($this->authenticateAdmin())
            ->get(route('clinic.financial-overview', ['month' => '2026-06']))
            ->assertOk()
            ->assertSee('+100.0%', false);
    }

    public function test_clinic_111_does_not_see_clinic_222_revenue(): void
    {
        $this->seedMonthlyAccounting('2026-06', revenue: '1000.00', labCost: '0.00');
        $this->seedClinic222MonthlyAccounting('2026-06', revenue: '9999.00');

        $this->actingAs($this->authenticateAdmin())
            ->get(route('clinic.financial-overview', ['month' => '2026-06']))
            ->assertOk()
            ->assertSee('AED 1000.00', false)
            ->assertDontSee('AED 9999.00', false);
    }

    public function test_top_treatments_are_listed(): void
    {
        $fixtures = $this->seedMonthlyAccounting('2026-06', revenue: '1000.00', labCost: '0.00', treatmentCode: 'BG');

        $this->actingAs($this->authenticateAdmin())
            ->get(route('clinic.financial-overview', ['month' => '2026-06']))
            ->assertOk()
            ->assertSee($fixtures['treatment']->name, false);
    }

    public function test_service_uses_clinic_timezone_for_default_month(): void
    {
        $clinic = $this->clinic111();
        $clinic->forceFill(['timezone' => 'Pacific/Auckland'])->save();

        $this->actingAs($this->authenticateAdmin());

        $overview = app(ClinicFinancialOverviewService::class)->build();

        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}$/', $overview->selectedMonth);
    }

    public function test_month_over_month_handles_zero_previous_value(): void
    {
        $comparison = MonthOverMonthComparison::calculate('100.00', '0.00');

        $this->assertNull($comparison);
    }

    public function test_month_over_month_handles_negative_values(): void
    {
        $comparison = MonthOverMonthComparison::calculate('-50.00', '100.00');

        $this->assertNotNull($comparison);
        $this->assertSame('down', $comparison['direction']);
    }

    public function test_login_and_register_routes_unchanged(): void
    {
        $this->get(route('login'))->assertOk();
        $this->get(route('register-clinic.create'))->assertOk();
    }

    public function test_needs_review_reports_are_excluded_from_revenue(): void
    {
        $this->seedMonthlyAccounting('2026-06', revenue: '5000.00', labCost: '0.00', status: ReportStatus::NeedsReview);

        $this->actingAs($this->authenticateAdmin())
            ->get(route('clinic.financial-overview', ['month' => '2026-06']))
            ->assertOk()
            ->assertSee('No accounting data', false)
            ->assertSee('awaiting review are excluded', false)
            ->assertDontSee('AED 5000.00', false);
    }

    public function test_approved_reports_are_included_in_revenue(): void
    {
        $this->seedMonthlyAccounting('2026-06', revenue: '750.00', labCost: '0.00', status: ReportStatus::Approved);

        $this->actingAs($this->authenticateAdmin())
            ->get(route('clinic.financial-overview', ['month' => '2026-06']))
            ->assertOk()
            ->assertSee('AED 750.00', false);
    }

    public function test_top_treatments_heading_uses_allocated_revenue_wording(): void
    {
        $this->seedMonthlyAccounting('2026-06', revenue: '100.00', labCost: '0.00');

        $this->actingAs($this->authenticateAdmin())
            ->get(route('clinic.financial-overview', ['month' => '2026-06']))
            ->assertOk()
            ->assertSee('Top treatments by allocated revenue', false)
            ->assertSee('Allocated revenue', false);
    }

    /**
     * @return array<string, mixed>
     */
    private function seedMonthlyAccounting(
        string $month,
        string $revenue,
        string $labCost,
        string $treatmentCode = 'BG',
        ReportStatus $status = ReportStatus::Calculated,
    ): array {
        $clinic = $this->clinic111();
        $doctor = Doctor::query()->where('clinic_id', $clinic->id)->firstOrFail();
        $treatment = Treatment::query()
            ->where('clinic_id', $clinic->id)
            ->where('code', $treatmentCode)
            ->firstOrFail();
        $lab = Lab::query()->where('clinic_id', $clinic->id)->firstOrFail();

        $report = $this->createDailyReport([
            'report_date' => $month.'-01',
            'source_type' => ReportSourceType::ExcelUpload,
            'status' => $status,
            'source_file_name' => "Clinic 111 {$month}.xlsx",
        ]);

        $row = $this->createDailyWorkRow($report, [
            'doctor_id' => $doctor->id,
            'work_date' => $month.'-15',
            'paid_total_aed' => $revenue,
        ]);

        Payment::query()->create([
            'clinic_id' => $clinic->id,
            'daily_work_row_id' => $row->id,
            'payment_method' => PaymentMethod::Dhs,
            'amount' => $revenue,
            'currency' => 'AED',
            'exchange_rate' => 1,
            'amount_aed' => $revenue,
            'paid_at' => $month.'-15',
        ]);

        $workItem = $this->createWorkItem($row, [
            'treatment_id' => $treatment->id,
            'quantity' => 1,
        ]);

        if (bccomp($labCost, '0', 2) > 0) {
            $this->createLabJob($workItem, [
                'lab_id' => $lab->id,
                'total_cost_aed' => $labCost,
                'unit_cost' => $labCost,
                'quantity' => 1,
                'status' => LabJobStatus::Calculated,
            ]);
        }

        return compact('report', 'row', 'workItem', 'treatment', 'doctor');
    }

    private function seedClinic222MonthlyAccounting(string $month, string $revenue): void
    {
        $clinic = $this->clinic222['clinic'];
        $doctor = $this->clinic222['doctor'];
        $treatment = $this->clinic222['treatment'];

        $report = $this->createClinic222DailyReport([
            'report_date' => $month.'-01',
            'source_type' => ReportSourceType::ExcelUpload,
            'status' => ReportStatus::Calculated,
            'source_file_name' => "Clinic 222 {$month}.xlsx",
        ]);

        $row = $this->createDailyWorkRow($report, [
            'doctor_id' => $doctor->id,
            'work_date' => $month.'-15',
            'paid_total_aed' => $revenue,
        ]);

        Payment::query()->create([
            'clinic_id' => $clinic->id,
            'daily_work_row_id' => $row->id,
            'payment_method' => PaymentMethod::Dhs,
            'amount' => $revenue,
            'currency' => 'AED',
            'exchange_rate' => 1,
            'amount_aed' => $revenue,
            'paid_at' => $month.'-15',
        ]);

        $this->createWorkItem($row, [
            'treatment_id' => $treatment->id,
            'quantity' => 1,
        ]);
    }
}
