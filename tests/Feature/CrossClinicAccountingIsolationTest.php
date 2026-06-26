<?php

namespace Tests\Feature;

use App\Enums\PaymentMethod;
use App\Enums\ReportSourceType;
use App\Enums\ReportStatus;
use App\Models\DailyReport;
use App\Models\LabJob;
use App\Models\Payment;
use App\Models\User;
use App\Models\WorkItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CrossClinicAccountingIsolationTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<string, mixed> */
    private array $clinic222;

    private DailyReport $report111;

    private DailyReport $report222;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccountingData();
        $this->clinic222 = $this->seedClinic222Tenant();

        $this->report111 = $this->createDailyReport([
            'report_date' => '2026-06-01',
            'source_type' => ReportSourceType::ExcelUpload,
            'source_file_name' => 'Clinic 111 June 2026.xlsm',
            'status' => ReportStatus::Calculated,
        ]);

        $this->report222 = $this->createClinic222AccountingReport();
    }

    public function test_clinic_111_user_cannot_view_clinic_222_report_via_api(): void
    {
        $this->actingAsRole('admin');

        $this->getJson("/api/daily-reports/{$this->report222->id}")
            ->assertNotFound();

        $this->getJson("/api/daily-reports/{$this->report111->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $this->report111->id);
    }

    public function test_clinic_222_user_cannot_view_clinic_111_report_via_api(): void
    {
        $this->actingAs($this->clinic222['admin']);

        $this->getJson("/api/daily-reports/{$this->report111->id}")
            ->assertNotFound();

        $this->getJson("/api/daily-reports/{$this->report222->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $this->report222->id);
    }

    public function test_import_index_lists_only_current_clinic_reports(): void
    {
        $admin111 = User::query()->where('email', 'admin@clinic.test')->firstOrFail();

        $this->actingAs($admin111)
            ->get(route('imports.index'))
            ->assertOk()
            ->assertSee('Clinic 111 June 2026.xlsm')
            ->assertDontSee('Clinic 222 June 2026.xlsm');

        $this->actingAs($this->clinic222['admin'])
            ->get(route('imports.index'))
            ->assertOk()
            ->assertSee('Clinic 222 June 2026.xlsm')
            ->assertDontSee('Clinic 111 June 2026.xlsm');
    }

    public function test_cross_clinic_report_delete_returns_not_found(): void
    {
        $admin111 = User::query()->where('email', 'admin@clinic.test')->firstOrFail();

        $this->actingAs($admin111)
            ->delete(route('imports.destroy', $this->report222))
            ->assertNotFound();

        $this->assertDatabaseHas('daily_reports', ['id' => $this->report222->id]);
    }

    public function test_cross_clinic_extraction_log_returns_not_found(): void
    {
        $admin111 = User::query()->where('email', 'admin@clinic.test')->firstOrFail();

        $this->actingAs($admin111)
            ->get(route('logs.extraction', $this->report222))
            ->assertNotFound();
    }

    public function test_clinic_111_monthly_income_excludes_clinic_222_payments(): void
    {
        $this->actingAsRole('admin');

        $summary111 = collect($this->getJson('/api/monthly-income?month=2026-06')->json('data'));
        $doctor222Code = $this->clinic222['doctor']->code;

        $this->assertFalse(
            $summary111->contains(fn (array $row) => ($row['doctor_name'] ?? '') === $doctor222Code),
        );

        $this->actingAs($this->clinic222['admin']);

        $summary222 = collect($this->getJson('/api/monthly-income?month=2026-06')->json('data'));
        $jack = $summary222->first(fn (array $row) => str_contains((string) ($row['doctor_name'] ?? ''), 'JACK'));

        $this->assertNull($jack);
    }

    public function test_clinic_222_report_payload_excludes_clinic_111_nested_records(): void
    {
        $this->actingAs($this->clinic222['admin']);

        $response = $this->getJson("/api/daily-reports/{$this->report222->id}")->assertOk();
        $rows = $response->json('data.daily_work_rows');

        $this->assertCount(1, $rows);
        $this->assertSame('C222_DOC', $rows[0]['doctor']['code']);
        $this->assertCount(1, $rows[0]['payments']);
        $this->assertCount(1, $rows[0]['work_items']);
        $this->assertNotNull($rows[0]['work_items'][0]['lab_job']);
    }

    private function createClinic222AccountingReport(): DailyReport
    {
        $report = $this->createClinic222DailyReport([
            'report_date' => '2026-06-01',
            'source_type' => ReportSourceType::ExcelUpload,
            'source_file_name' => 'Clinic 222 June 2026.xlsm',
            'status' => ReportStatus::Calculated,
        ]);

        $workRow = $this->createDailyWorkRow($report, [
            'doctor_id' => $this->clinic222['doctor']->id,
            'work_date' => '2026-06-10',
            'treatment_text' => 'C222_TX x 1',
            'paid_total_aed' => '500.00',
        ]);

        Payment::query()->create([
            'clinic_id' => $report->clinic_id,
            'daily_work_row_id' => $workRow->id,
            'payment_method' => PaymentMethod::Dhs,
            'amount' => '500.00',
            'currency' => 'AED',
            'amount_aed' => '500.00',
            'paid_at' => '2026-06-10',
        ]);

        $workItem = $this->createWorkItem($workRow, [
            'treatment_id' => $this->clinic222['treatment']->id,
            'quantity' => 1,
            'confidence' => 100,
        ]);

        $this->createLabJob($workItem, [
            'lab_id' => $this->clinic222['lab']->id,
            'lab_price_id' => $this->clinic222['price']->id,
            'quantity' => 1,
            'unit_cost' => '100.00',
            'total_cost_aed' => '100.00',
        ]);

        return $report->fresh(['dailyWorkRows.payments', 'dailyWorkRows.workItems.labJob']);
    }
}
