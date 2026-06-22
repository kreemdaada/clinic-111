<?php

namespace Tests\Unit;

use App\Models\DailyReport;
use App\Models\DailyWorkRow;
use App\Models\Doctor;
use App\Models\Treatment;
use App\Models\WorkItem;
use App\Services\Accounting\LabJobCalculationService;
use Tests\TestCase;

class DoctorLabBillingTest extends TestCase
{
    private LabJobCalculationService $labJobCalculationService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccountingData();
        $this->labJobCalculationService = app(LabJobCalculationService::class);
    }

    public function test_dr_wa_impl_does_not_create_lab_job(): void
    {
        $this->assertNoLabJobForDoctorTreatment('WA', 'IMPL', 1);
    }

    public function test_dr_puriya_abt_does_not_create_lab_job(): void
    {
        $this->assertNoLabJobForDoctorTreatment('PURIYA', 'ABT', 1);
    }

    public function test_dr_puriya_mc_creates_lab_job(): void
    {
        $workItem = $this->calculateLabJobForDoctorTreatment('PURIYA', 'MC', 2);

        $this->assertNotNull($workItem->labJob);
        $this->assertSame('210.00', number_format((float) $workItem->labJob->total_cost_aed, 2, '.', ''));
    }

    public function test_dr_jack_abt_creates_lab_job(): void
    {
        $workItem = $this->calculateLabJobForDoctorTreatment('JACK', 'ABT', 1);

        $this->assertNotNull($workItem->labJob);
        $this->assertSame('511.00', number_format((float) $workItem->labJob->unit_cost, 2, '.', ''));
    }

    public function test_dr_puriya_remov_creates_lab_job(): void
    {
        $workItem = $this->calculateLabJobForDoctorTreatment('PURIYA', 'REMOV', 1);

        $this->assertNotNull($workItem->labJob);
        $this->assertSame('100.00', number_format((float) $workItem->labJob->total_cost_aed, 2, '.', ''));
    }

    private function assertNoLabJobForDoctorTreatment(string $doctorCode, string $treatmentCode, int $quantity): void
    {
        $workItem = $this->calculateLabJobForDoctorTreatment($doctorCode, $treatmentCode, $quantity);

        $this->assertNull($workItem->labJob);
    }

    private function calculateLabJobForDoctorTreatment(string $doctorCode, string $treatmentCode, int $quantity): WorkItem
    {
        $doctor = Doctor::query()->where('code', $doctorCode)->firstOrFail();
        $treatment = Treatment::query()->where('code', $treatmentCode)->firstOrFail();

        $dailyReport = DailyReport::query()->create([
            'report_date' => '2026-01-15',
            'source_type' => 'manual_entry',
            'status' => 'parsed',
        ]);

        $dailyWorkRow = DailyWorkRow::query()->create([
            'daily_report_id' => $dailyReport->id,
            'doctor_id' => $doctor->id,
            'work_date' => '2026-01-15',
            'treatment_text' => "{$treatmentCode} x {$quantity}",
        ]);

        $workItem = WorkItem::query()->create([
            'daily_work_row_id' => $dailyWorkRow->id,
            'treatment_id' => $treatment->id,
            'quantity' => $quantity,
        ]);

        $this->labJobCalculationService->calculateForWorkRow($dailyWorkRow);

        return $workItem->refresh()->load('labJob');
    }
}
