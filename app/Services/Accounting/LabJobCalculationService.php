<?php

namespace App\Services\Accounting;

use App\Enums\LabJobStatus;
use App\Models\DailyReport;
use App\Models\DailyWorkRow;
use App\Models\Lab;
use App\Models\LabJob;
use App\Models\WorkItem;
use App\Support\MoneyCalculator;
use Illuminate\Support\Collection;

/**
 * Calculates JOB (lab cost) as SUM(quantity × unit_cost) per work item.
 */
class LabJobCalculationService
{
    public function __construct(
        private readonly LabPriceResolver $labPriceResolver,
    ) {}

    public function calculateForReport(DailyReport $dailyReport): void
    {
        $activeLabs = Lab::query()->where('is_active', true)->get();

        $dailyReport->load([
            'dailyWorkRows.doctor',
            'dailyWorkRows.workItems.treatment',
        ]);

        foreach ($dailyReport->dailyWorkRows as $dailyWorkRow) {
            $this->calculateForWorkRow($dailyWorkRow, $activeLabs);
        }
    }

    public function calculateForWorkRow(DailyWorkRow $dailyWorkRow, ?Collection $activeLabs = null): void
    {
        if ($activeLabs === null) {
            $activeLabs = Lab::query()->where('is_active', true)->get();
        }
        $dailyWorkRow->loadMissing(['doctor', 'workItems.treatment']);

        foreach ($dailyWorkRow->workItems as $workItem) {
            $this->calculateForWorkItem($workItem, $dailyWorkRow, $activeLabs);
        }
    }

    private function calculateForWorkItem(
        WorkItem $workItem,
        DailyWorkRow $dailyWorkRow,
        Collection $activeLabs,
    ): void {
        if ($workItem->labJob !== null) {
            $workItem->labJob->delete();
        }

        $treatment = $workItem->treatment;

        if (! $treatment->has_lab_cost) {
            return;
        }

        $doctor = $dailyWorkRow->doctor;
        $lab = $this->labPriceResolver->resolveLabForDoctor($doctor, $activeLabs);
        $labPrice = $this->labPriceResolver->resolve(
            $doctor,
            $treatment,
            $lab,
            $dailyWorkRow->work_date,
        );

        if ($labPrice === null) {
            return;
        }

        $unitCostAed = MoneyCalculator::convertToAed(
            (string) $labPrice->unit_cost,
            $labPrice->currency,
        );

        $totalCostAed = MoneyCalculator::multiply($unitCostAed, $workItem->quantity);

        LabJob::query()->create([
            'work_item_id' => $workItem->id,
            'lab_id' => $lab->id,
            'lab_price_id' => $labPrice->id,
            'quantity' => $workItem->quantity,
            'unit_cost' => $unitCostAed,
            'total_cost_aed' => $totalCostAed,
            'status' => LabJobStatus::Calculated,
        ]);
    }
}
