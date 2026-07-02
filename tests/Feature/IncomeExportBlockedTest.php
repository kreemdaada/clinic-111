<?php

namespace Tests\Feature;

use App\Models\Doctor;
use App\Models\Treatment;
use App\Services\Accounting\LabJobCalculationService;
use Tests\TestCase;

class IncomeExportBlockedTest extends TestCase
{
    public function test_blocked_income_export_redirects_with_friendly_guidance(): void
    {
        $this->seedAccountingData();

        $doctor = Doctor::query()->where('code', 'JACK')->firstOrFail();
        $treatment = Treatment::query()->where('code', 'ABT')->firstOrFail();

        $report = $this->createDailyReport([
            'report_date' => '2026-07-01',
            'source_type' => 'manual_entry',
            'source_file_name' => 'JACK · 1 Jul – 28 Jul 2026',
            'status' => 'needs_review',
        ]);

        $workRow = $this->createDailyWorkRow($report, [
            'doctor_id' => $doctor->id,
            'work_date' => '2026-07-01',
            'treatment_text' => 'ABT x 1',
            'dhs_amount' => '0.00',
            'cheque_amount' => '0.00',
            'tabby_amount' => '0.00',
            'usd_amount' => '0.00',
            'usd_to_aed_amount' => '0.00',
            'visa_amount' => '0.00',
            'paid_total_aed' => '0.00',
        ]);

        $this->createWorkItem($workRow, [
            'treatment_id' => $treatment->id,
            'quantity' => 1,
        ]);

        app(LabJobCalculationService::class)->calculateForWorkRow($workRow);

        $this->authenticateAdmin();

        $response = $this->from(route('daily-report.edit', $report))
            ->get(route('imports.income', $report));

        $response->assertRedirect(route('daily-report.edit', $report));
        $response->assertSessionHasErrors('income_export');

        $messages = session('errors')->get('income_export');

        $this->assertStringContainsString('cannot be downloaded yet', $messages[0]);
        $this->assertStringContainsString('JACK', implode(' ', $messages));
        $this->assertStringContainsString('External lab cost', implode(' ', $messages));
        $this->assertStringContainsString('daily report', implode(' ', $messages));
    }
}
