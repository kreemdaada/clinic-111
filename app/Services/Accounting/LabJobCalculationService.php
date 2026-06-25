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
 *
 * Only runs when {@see LabBillingResolver} allows billing for doctor + treatment.
 */
class LabJobCalculationService
{
    public function __construct(
        private readonly LabPriceResolver $labPriceResolver,
        private readonly LabBillingResolver $labBillingResolver,
    ) {}

    /**
     * Calculate lab jobs for every work item on every row in a report.
     *
     * @param  DailyReport  $dailyReport  Report with daily work rows to process.
     */
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

    /**
     * Calculate lab jobs for all work items on one daily work row.
     *
     * @param  DailyWorkRow  $dailyWorkRow  Row with work items loaded or loadable.
     * @param  Collection<int, Lab>|null  $activeLabs  Optional preloaded labs.
     */
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

    /**
     * Resolve price and create or replace one lab job for a single work item.
     *
     * Deletes existing lab job first. No-op when treatment has no lab cost or no price found.
     *
     * @param  WorkItem  $workItem  Parsed treatment line.
     * @param  DailyWorkRow  $dailyWorkRow  Parent row (doctor + work_date for pricing).
     * @param  Collection<int, Lab>  $activeLabs  Active labs for doctor resolution.
     */
    private function calculateForWorkItem(
        WorkItem $workItem,
        DailyWorkRow $dailyWorkRow,
        Collection $activeLabs,
    ): void {
        if ($workItem->labJob !== null) {
            $workItem->labJob->delete();
        }

        $treatment = $workItem->treatment;
        $doctor = $dailyWorkRow->doctor;

        if (! $this->labBillingResolver->shouldBillLabJob($doctor, $treatment)) {
            return;
        }

        $resolved = $this->labPriceResolver->resolveWithLabFallback(
            $doctor,
            $treatment,
            $activeLabs,
            $dailyWorkRow->work_date,
        );

        if ($resolved === null) {
            return;
        }

        $lab = $resolved['lab'];
        $labPrice = $resolved['price'];

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
