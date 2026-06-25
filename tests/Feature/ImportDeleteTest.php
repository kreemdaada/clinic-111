<?php

namespace Tests\Feature;

use App\Enums\ReportSourceType;
use App\Enums\ReportStatus;
use App\Models\DailyReport;
use App\Models\DailyWorkRow;
use App\Models\Doctor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImportDeleteTest extends TestCase
{
    use RefreshDatabase;

    public function test_accountant_can_delete_import_from_recent_list(): void
    {
        $this->seed();

        Storage::fake('local');

        $user = User::query()->where('email', 'accountant@clinic.test')->firstOrFail();
        $report = DailyReport::query()->create([
            'report_date' => '2026-06-01',
            'source_type' => ReportSourceType::ExcelUpload,
            'source_file_name' => 'daily report June 2026.xlsm',
            'status' => ReportStatus::Calculated,
        ]);

        Storage::disk('local')->put('import-extractions/report-' . $report->id . '.json', '{}');

        DailyWorkRow::query()->create([
            'daily_report_id' => $report->id,
            'doctor_id' => Doctor::query()->firstOrFail()->id,
            'work_date' => '2026-06-01',
            'paid_total_aed' => '100.00',
        ]);

        $this->actingAs($user)
            ->delete(route('imports.destroy', $report))
            ->assertRedirect(route('imports.index'))
            ->assertSessionHas('status');

        $this->assertDatabaseMissing('daily_reports', ['id' => $report->id]);
        $this->assertDatabaseMissing('daily_work_rows', ['daily_report_id' => $report->id]);
        $logPath = 'import-extractions/report-' . $report->id . '.json';
        $this->assertFalse(Storage::disk('local')->exists($logPath));
    }

    public function test_approved_import_cannot_be_deleted(): void
    {
        $this->seed();

        $user = User::query()->where('email', 'accountant@clinic.test')->firstOrFail();
        $report = DailyReport::query()->create([
            'report_date' => '2026-06-01',
            'source_type' => ReportSourceType::ExcelUpload,
            'source_file_name' => 'daily report June 2026.xlsm',
            'status' => ReportStatus::Approved,
        ]);

        $this->actingAs($user)
            ->delete(route('imports.destroy', $report))
            ->assertRedirect()
            ->assertSessionHasErrors('delete');

        $this->assertDatabaseHas('daily_reports', ['id' => $report->id]);
    }
}
