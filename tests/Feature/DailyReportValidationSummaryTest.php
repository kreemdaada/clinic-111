<?php

namespace Tests\Feature;

use App\Models\DailyReportImportWarning;
use App\Models\Doctor;
use Tests\TestCase;

class DailyReportValidationSummaryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccountingData();
        $this->actingAsRole('accountant');
    }

    public function test_validation_summary_endpoint_returns_warnings(): void
    {
        $doctor = Doctor::query()->where('code', 'RIYAD')->firstOrFail();

        $dailyReport = $this->createDailyReport([
            'report_date' => '2026-01-15',
            'source_type' => 'excel_upload',
            'status' => 'needs_review',
        ]);

        $workRow = $this->createDailyWorkRow($dailyReport, [
            'doctor_id' => $doctor->id,
            'work_date' => '2026-01-15',
            'excel_row_number' => 25,
            'treatment_text' => 'zircon 2',
        ]);

        DailyReportImportWarning::query()->create([
            'daily_report_id' => $dailyReport->id,
            'daily_work_row_id' => $workRow->id,
            'excel_row_number' => 25,
            'doctor_code' => 'Dr Riyad',
            'treatment_text' => 'zircon 2',
            'warning_code' => 'invalid_format',
            'message' => 'Invalid format. Use ZIR x 2',
        ]);

        $response = $this->getJson("/api/daily-reports/{$dailyReport->id}/validation-summary");

        $response->assertOk()
            ->assertJsonPath('data.total_rows', 1)
            ->assertJsonPath('data.warnings_count', 1)
            ->assertJsonPath('data.warnings.0.excel_row', 25)
            ->assertJsonPath('data.warnings.0.doctor', 'Dr Riyad')
            ->assertJsonPath('data.warnings.0.treatment_text', 'zircon 2')
            ->assertJsonPath('data.warnings.0.message', 'Invalid format. Use ZIR x 2');
    }
}
