<?php

namespace Tests\Unit;

use App\Models\DailyReport;
use App\Models\DailyWorkRow;
use App\Models\Doctor;
use App\Models\Treatment;
use App\Models\WorkItem;
use App\Services\Accounting\LabJobCalculationService;
use Tests\TestCase;

class NonLabTreatmentJobTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccountingData();
        $this->authenticateAdmin();
    }

    public function test_cf_and_sxp_are_persisted_as_work_items_without_lab_jobs(): void
    {
        $doctor = Doctor::query()->where('code', 'JACK')->firstOrFail();
        $dailyReport = $this->createDailyReport([
            'report_date' => '2026-01-15',
            'source_type' => 'manual_entry',
            'status' => 'parsed',
        ]);

        $dailyWorkRow = $this->createDailyWorkRow($dailyReport, [
            'doctor_id' => $doctor->id,
            'work_date' => '2026-01-15',
            'treatment_text' => 'SxP x1 + CF x3',
        ]);

        app(\App\Services\Accounting\TreatmentParserService::class)->parseAndPersist($dailyWorkRow);

        $this->assertSame(2, $dailyWorkRow->workItems()->count());
        $this->assertSame(0, $dailyWorkRow->workItems()->whereHas('labJob')->count());
    }

    public function test_cf_and_sxp_do_not_create_lab_jobs(): void
    {
        $doctor = Doctor::query()->where('code', 'JACK')->firstOrFail();
        $dailyReport = $this->createDailyReport([
            'report_date' => '2026-01-15',
            'source_type' => 'manual_entry',
            'status' => 'parsed',
        ]);

        foreach (['CF', 'SXP', 'RCT'] as $code) {
            $treatment = Treatment::query()->where('code', $code)->firstOrFail();

            $dailyWorkRow = $this->createDailyWorkRow($dailyReport, [
                'doctor_id' => $doctor->id,
                'work_date' => '2026-01-15',
                'treatment_text' => "{$code} x 3",
            ]);

            $workItem = $this->createWorkItem($dailyWorkRow, [
                'treatment_id' => $treatment->id,
                'quantity' => 3,
            ]);

            app(LabJobCalculationService::class)->calculateForWorkRow($dailyWorkRow);

            $workItem->refresh()->load('labJob');

            $this->assertNull($workItem->labJob, "{$code} must not create a lab job");
        }
    }
}
