<?php

namespace Tests\Unit;

use App\Models\DailyReport;
use App\Models\DailyWorkRow;
use App\Models\Doctor;
use App\Models\Treatment;
use App\Models\WorkItem;
use App\Services\Accounting\LabJobCalculationService;
use Tests\TestCase;

class LabJobCalculationServiceTest extends TestCase
{
    private LabJobCalculationService $labJobCalculationService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccountingData();
        $this->labJobCalculationService = app(LabJobCalculationService::class);
    }

    public function test_zir_times_four_for_dr_riyad_costs_1600_aed(): void
    {
        $doctorRiyad = Doctor::query()->where('code', 'RIYAD')->firstOrFail();
        $treatmentZir = Treatment::query()->where('code', 'ZIR')->firstOrFail();

        $dailyReport = DailyReport::query()->create([
            'report_date' => '2026-01-15',
            'source_type' => 'manual_entry',
            'status' => 'parsed',
        ]);

        $dailyWorkRow = DailyWorkRow::query()->create([
            'daily_report_id' => $dailyReport->id,
            'doctor_id' => $doctorRiyad->id,
            'work_date' => '2026-01-15',
            'treatment_text' => 'ZIR x 4',
        ]);

        $workItem = WorkItem::query()->create([
            'daily_work_row_id' => $dailyWorkRow->id,
            'treatment_id' => $treatmentZir->id,
            'quantity' => 4,
        ]);

        $this->labJobCalculationService->calculateForWorkRow($dailyWorkRow);

        $workItem->refresh()->load('labJob');

        $this->assertNotNull($workItem->labJob);
        $this->assertSame('1600.00', number_format((float) $workItem->labJob->total_cost_aed, 2, '.', ''));
        $this->assertSame(4, $workItem->labJob->quantity);
        $this->assertSame('400.00', number_format((float) $workItem->labJob->unit_cost, 2, '.', ''));
    }
}
