<?php

namespace Tests\Feature;

use App\Enums\ReportSourceType;
use App\Enums\ReportStatus;
use App\Models\DailyReport;
use App\Models\DailyWorkRow;
use App\Models\Doctor;
use App\Models\Treatment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DailyReportRowEditTest extends TestCase
{
    use RefreshDatabase;

    public function test_accountant_can_update_imported_work_row_via_editor_api(): void
    {
        $this->seed();

        $user = User::query()->where('email', 'accountant@clinic.test')->firstOrFail();
        $doctor = Doctor::query()->where('code', 'JACK')->firstOrFail();
        $treatment = Treatment::query()->where('code', 'MC')->firstOrFail();

        $report = $this->createDailyReport([
            'report_date' => '2026-06-01',
            'source_type' => ReportSourceType::ExcelUpload,
            'source_file_name' => 'daily report June 2026.xlsm',
            'status' => ReportStatus::Calculated,
        ]);

        $workRow = $this->createDailyWorkRow($report, [
            'doctor_id' => $doctor->id,
            'work_date' => '2026-06-08',
            'treatment_text' => 'MC x 2',
            'dhs_amount' => '100.00',
            'cheque_amount' => '0.00',
            'tabby_amount' => '2800.00',
            'usd_amount' => '0.00',
            'usd_to_aed_amount' => '0.00',
            'visa_amount' => '1500.00',
            'paid_total_aed' => '4400.00',
            'raw_data_json' => ['sheet_day' => 8, 'doctor' => 'JACK'],
        ]);

        $response = $this->actingAs($user)->postJson(route('daily-report.rows.save', $report), [
            'work_row_id' => $workRow->id,
            'doctor_id' => $doctor->id,
            'day' => 8,
            'dhs_amount' => '350',
            'cheque_amount' => '0',
            'tabby_amount' => '0',
            'usd_amount' => '0',
            'visa_amount' => '1500',
            'treatment_lines' => [
                ['code' => $treatment->code, 'quantity' => 2],
            ],
        ]);

        $response->assertOk();

        $workRow->refresh();

        $this->assertSame('350.00', (string) $workRow->dhs_amount);
        $this->assertSame('0.00', (string) $workRow->tabby_amount);
        $this->assertSame('1850.00', (string) $workRow->paid_total_aed);
        $this->assertTrue((bool) ($workRow->raw_data_json['corrected_in_editor'] ?? false));
    }

    public function test_rows_api_returns_treatment_lines_for_editing(): void
    {
        $this->seed();

        $user = User::query()->where('email', 'accountant@clinic.test')->firstOrFail();
        $doctor = Doctor::query()->where('code', 'JACK')->firstOrFail();
        $treatment = Treatment::query()->where('code', 'ZIR')->firstOrFail();

        $report = $this->createDailyReport([
            'report_date' => '2026-06-01',
            'source_type' => ReportSourceType::ExcelUpload,
            'source_file_name' => 'daily report June 2026.xlsm',
            'status' => ReportStatus::Calculated,
        ]);

        $workRow = $this->createDailyWorkRow($report, [
            'doctor_id' => $doctor->id,
            'work_date' => '2026-06-09',
            'treatment_text' => 'ZIR x 3',
            'visa_amount' => '1050.00',
            'paid_total_aed' => '1050.00',
            'raw_data_json' => ['sheet_day' => 9],
        ]);

        $this->createWorkItem($workRow, [
            'treatment_id' => $treatment->id,
            'quantity' => 3,
        ]);

        $response = $this->actingAs($user)->getJson(route('daily-report.rows', [
            'dailyReport' => $report,
            'doctor_id' => $doctor->id,
            'day' => 9,
        ]));

        $response->assertOk();
        $response->assertJsonPath('data.0.id', $workRow->id);
        $response->assertJsonPath('data.0.treatment_lines.0.code', 'ZIR');
        $response->assertJsonPath('data.0.treatment_lines.0.quantity', 3);
        $response->assertJsonPath('data.0.source', 'import');
    }

    public function test_rows_endpoint_returns_day_counts_without_day_parameter(): void
    {
        $this->seed();

        $user = User::query()->where('email', 'accountant@clinic.test')->firstOrFail();
        $doctor = Doctor::query()->where('code', 'JACK')->firstOrFail();

        $report = $this->createDailyReport([
            'report_date' => '2026-06-01',
            'source_type' => ReportSourceType::ManualEntry,
            'source_file_name' => 'JACK · 1 Jun – 30 Jun 2026',
            'status' => ReportStatus::NeedsReview,
        ]);

        $this->createDailyWorkRow($report, [
            'doctor_id' => $doctor->id,
            'work_date' => '2026-06-09',
            'treatment_text' => 'MC x 1',
            'dhs_amount' => '100.00',
            'paid_total_aed' => '100.00',
        ]);

        $this->createDailyWorkRow($report, [
            'doctor_id' => $doctor->id,
            'work_date' => '2026-06-12',
            'treatment_text' => 'ZIR x 1',
            'dhs_amount' => '200.00',
            'paid_total_aed' => '200.00',
        ]);

        $response = $this->actingAs($user)->getJson(route('daily-report.rows', [
            'dailyReport' => $report,
            'doctor_id' => $doctor->id,
        ]));

        $response->assertOk()
            ->assertJsonPath('data', [])
            ->assertJsonPath('day_counts.9', 1)
            ->assertJsonPath('day_counts.12', 1);
    }

    public function test_accountant_can_open_editor_for_excel_import_report(): void
    {
        $this->seed();

        $user = User::query()->where('email', 'accountant@clinic.test')->firstOrFail();

        $report = $this->createDailyReport([
            'report_date' => '2026-06-01',
            'source_type' => ReportSourceType::ExcelUpload,
            'source_file_name' => 'daily report June 2026.xlsm',
            'status' => ReportStatus::Calculated,
        ]);

        $this->actingAs($user)
            ->get(route('daily-report.edit', $report))
            ->assertOk()
            ->assertSee('Daily Report');
    }

    public function test_accountant_editor_hides_add_doctor_and_commission_percentages(): void
    {
        $this->seed();

        $accountant = User::query()->where('email', 'accountant@clinic.test')->firstOrFail();

        $report = $this->createDailyReport([
            'report_date' => '2026-06-01',
            'source_type' => ReportSourceType::ManualEntry,
            'source_file_name' => 'JACK · 1 Jun – 30 Jun 2026',
            'status' => ReportStatus::NeedsReview,
        ]);

        $this->actingAs($accountant)
            ->get(route('daily-report.edit', $report))
            ->assertOk()
            ->assertDontSee('id="dr-add-doctor-open"', false)
            ->assertDontSee('Add doctor', false)
            ->assertSee('JACK', false)
            ->assertDontSee('>35%<', false)
            ->assertSee('Save entry', false);
    }

    public function test_admin_editor_shows_add_doctor_and_commission_percentages(): void
    {
        $this->seed();

        $admin = User::query()->where('email', 'admin@clinic.test')->firstOrFail();

        $report = $this->createDailyReport([
            'report_date' => '2026-06-01',
            'source_type' => ReportSourceType::ManualEntry,
            'source_file_name' => 'JACK · 1 Jun – 30 Jun 2026',
            'status' => ReportStatus::NeedsReview,
        ]);

        $this->actingAs($admin)
            ->get(route('daily-report.edit', $report))
            ->assertOk()
            ->assertSee('id="dr-add-doctor-open"', false)
            ->assertSee('35%', false);
    }

    public function test_admin_preview_calculates_lab_cost_for_zir_treatments(): void
    {
        $this->seed();

        $admin = User::query()->where('email', 'admin@clinic.test')->firstOrFail();
        $doctor = Doctor::query()->where('code', 'JACK')->firstOrFail();

        $report = $this->createDailyReport([
            'report_date' => '2026-06-01',
            'source_type' => ReportSourceType::ManualEntry,
            'source_file_name' => 'JACK · 1 Jun – 30 Jun 2026',
            'status' => ReportStatus::Uploaded,
        ]);

        $this->actingAs($admin)
            ->postJson(route('daily-report.preview', $report), [
                'doctor_id' => $doctor->id,
                'day' => 5,
                'dhs_amount' => '1000',
                'cheque_amount' => '0',
                'tabby_amount' => '0',
                'usd_amount' => '0',
                'visa_amount' => '0',
                'treatment_lines' => [
                    ['code' => 'ZIR', 'quantity' => 1],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.treatment_text', 'ZIR x 1')
            ->assertJsonPath('data.paid_total_aed', '1000.00')
            ->assertJsonPath('data.lab_total_aed', '360.00')
            ->assertJsonPath('data.net_total_aed', '640.00');
    }

    public function test_admin_can_save_new_manual_row_with_zir_treatment(): void
    {
        $this->seed();

        $admin = User::query()->where('email', 'admin@clinic.test')->firstOrFail();
        $doctor = Doctor::query()->where('code', 'JACK')->firstOrFail();
        $treatment = Treatment::query()->where('code', 'ZIR')->firstOrFail();

        $report = $this->createDailyReport([
            'report_date' => '2026-06-01',
            'source_type' => ReportSourceType::ManualEntry,
            'source_file_name' => 'JACK · 1 Jun – 30 Jun 2026',
            'status' => ReportStatus::Uploaded,
        ]);

        $response = $this->actingAs($admin)->postJson(route('daily-report.rows.save', $report), [
            'doctor_id' => $doctor->id,
            'day' => 5,
            'dhs_amount' => '500',
            'cheque_amount' => '0',
            'tabby_amount' => '0',
            'usd_amount' => '0',
            'visa_amount' => '0',
            'treatment_lines' => [
                ['code' => $treatment->code, 'quantity' => 2],
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.treatment_text', 'ZIR x 2');

        $workRow = DailyWorkRow::query()->findOrFail($response->json('data.id'));
        $workRow->load('workItems.treatment', 'workItems.labJob');

        $this->assertSame('500.00', (string) $workRow->paid_total_aed);
        $this->assertCount(1, $workRow->workItems);
        $this->assertSame('ZIR', $workRow->workItems->first()->treatment->code);
        $this->assertSame(2, $workRow->workItems->first()->quantity);
        $this->assertNotNull($workRow->workItems->first()->labJob);
        $this->assertSame('720.00', (string) $workRow->workItems->first()->labJob->total_cost_aed);
        $this->assertSame(ReportStatus::Calculated, $report->fresh()->status);
    }

    public function test_viewer_editor_is_read_only(): void
    {
        $this->seed();

        $viewer = User::query()->where('email', 'viewer@clinic.test')->firstOrFail();

        $report = $this->createDailyReport([
            'report_date' => '2026-06-01',
            'source_type' => ReportSourceType::ManualEntry,
            'source_file_name' => 'JACK · 1 Jun – 30 Jun 2026',
            'status' => ReportStatus::NeedsReview,
        ]);

        $this->actingAs($viewer)
            ->get(route('daily-report.edit', $report))
            ->assertOk()
            ->assertSee('View-only access', false)
            ->assertDontSee('id="dr-add-doctor-open"', false)
            ->assertDontSee('35%', false);
    }
}
