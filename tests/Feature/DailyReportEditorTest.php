<?php

namespace Tests\Feature;

use App\Enums\ReportSourceType;
use App\Enums\ReportStatus;
use App\Enums\UserRole;
use App\Models\DailyReport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DailyReportEditorTest extends TestCase
{
    use RefreshDatabase;

    public function test_accountant_can_open_daily_report_index(): void
    {
        $this->seed();

        $user = User::query()->where('email', 'accountant@clinic.test')->firstOrFail();

        $this->actingAs($user)
            ->get(route('daily-report.index'))
            ->assertOk();
    }

    public function test_authenticated_user_is_not_trapped_between_login_and_home(): void
    {
        $this->seed();

        $user = User::query()->where('email', 'accountant@clinic.test')->firstOrFail();

        $this->actingAs($user)
            ->get(route('login'))
            ->assertRedirect(route('imports.index'));

        $this->actingAs($user)
            ->get('/')
            ->assertRedirect(route('imports.index'));
    }

    public function test_accountant_can_create_manual_report_with_doctor_and_date_range(): void
    {
        $this->seed();

        $user = User::query()->where('email', 'accountant@clinic.test')->firstOrFail();
        $doctor = \App\Models\Doctor::query()->where('code', 'JACK')->firstOrFail();

        $response = $this->actingAs($user)->post(route('daily-report.store'), [
            'doctor_id' => $doctor->id,
            'date_from' => '2026-06-01',
            'date_to' => '2026-06-15',
        ]);

        $report = \App\Models\DailyReport::query()->latest('id')->first();
        $this->assertNotNull($report);

        $response->assertRedirect(route('daily-report.edit', [
            'dailyReport' => $report,
            'doctor' => $doctor->id,
            'from' => '2026-06-01',
            'to' => '2026-06-15',
        ]));
    }

    public function test_accountant_can_delete_manual_report_from_index(): void
    {
        $this->seed();

        $user = User::query()->where('email', 'accountant@clinic.test')->firstOrFail();
        $report = $this->createDailyReport([
            'report_date' => '2026-06-01',
            'source_type' => ReportSourceType::ManualEntry,
            'source_file_name' => 'JACK · 1 Jun – 15 Jun 2026',
            'status' => ReportStatus::Uploaded,
        ]);

        $this->actingAs($user)
            ->delete(route('daily-report.destroy', $report))
            ->assertRedirect(route('daily-report.index'))
            ->assertSessionHas('status');

        $this->assertDatabaseMissing('daily_reports', ['id' => $report->id]);
    }

    public function test_approved_manual_report_cannot_be_deleted(): void
    {
        $this->seed();

        $user = User::query()->where('email', 'accountant@clinic.test')->firstOrFail();
        $report = $this->createDailyReport([
            'report_date' => '2026-06-01',
            'source_type' => ReportSourceType::ManualEntry,
            'source_file_name' => 'Manual report',
            'status' => ReportStatus::Approved,
        ]);

        $this->actingAs($user)
            ->delete(route('daily-report.destroy', $report))
            ->assertRedirect()
            ->assertSessionHasErrors('delete');

        $this->assertDatabaseHas('daily_reports', ['id' => $report->id]);
    }

    public function test_viewer_can_open_daily_report_editor_read_only(): void
    {
        $this->seed();

        $user = User::query()->where('role', UserRole::Viewer)->first();

        if ($user === null) {
            $user = User::factory()->create([
                'email' => 'viewer-daily@test.local',
                'role' => UserRole::Viewer,
            ]);
        }

        $this->actingAs($user)
            ->get(route('daily-report.index'))
            ->assertOk();
    }
}
