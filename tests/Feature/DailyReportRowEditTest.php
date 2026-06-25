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

        $report = DailyReport::query()->create([
            'report_date' => '2026-06-01',
            'source_type' => ReportSourceType::ExcelUpload,
            'source_file_name' => 'daily report June 2026.xlsm',
            'status' => ReportStatus::Calculated,
        ]);

        $workRow = DailyWorkRow::query()->create([
            'daily_report_id' => $report->id,
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

        $report = DailyReport::query()->create([
            'report_date' => '2026-06-01',
            'source_type' => ReportSourceType::ExcelUpload,
            'source_file_name' => 'daily report June 2026.xlsm',
            'status' => ReportStatus::Calculated,
        ]);

        $workRow = DailyWorkRow::query()->create([
            'daily_report_id' => $report->id,
            'doctor_id' => $doctor->id,
            'work_date' => '2026-06-09',
            'treatment_text' => 'ZIR x 3',
            'visa_amount' => '1050.00',
            'paid_total_aed' => '1050.00',
            'raw_data_json' => ['sheet_day' => 9],
        ]);

        $workRow->workItems()->create([
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

    public function test_accountant_can_open_editor_for_excel_import_report(): void
    {
        $this->seed();

        $user = User::query()->where('email', 'accountant@clinic.test')->firstOrFail();

        $report = DailyReport::query()->create([
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
}
