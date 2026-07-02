<?php

namespace App\Services\Accounting;

use App\Models\DailyReport;
use App\Models\DailyWorkRow;
use App\Models\Nurse;
use App\Models\NurseCommission;
use App\Models\WorkItem;
use App\Support\AccountingScopedQuery;
use App\Support\MoneyCalculator;
use Illuminate\Support\Collection;

/**
 * Calculates historical nurse commission snapshots per work item (ADR-039).
 */
class NurseCommissionCalculationService
{
    public function __construct(
        private readonly NurseCommissionRateResolver $nurseCommissionRateResolver,
    ) {}

    public function calculateForReport(DailyReport $dailyReport): void
    {
        $clinicId = (int) $dailyReport->clinic_id;

        $workRows = AccountingScopedQuery::workRows($clinicId, $dailyReport->id)->get();

        foreach ($workRows as $dailyWorkRow) {
            $this->calculateForWorkRow($dailyWorkRow);
        }
    }

    public function calculateForWorkRow(DailyWorkRow $dailyWorkRow): void
    {
        $workItems = AccountingScopedQuery::workItems((int) $dailyWorkRow->clinic_id, $dailyWorkRow->id)
            ->with(['treatment', 'nurse'])
            ->get();

        foreach ($workItems as $workItem) {
            $this->calculateForWorkItem($workItem);
        }
    }

    private function calculateForWorkItem(WorkItem $workItem): void
    {
        AccountingScopedQuery::nurseCommissions((int) $workItem->clinic_id, $workItem->id)->delete();

        $workItem->loadMissing('treatment');
        $treatment = $workItem->treatment;

        if (! $treatment->requires_nurse_commission) {
            return;
        }

        if ($workItem->nurse_id === null) {
            return;
        }

        $nurse = Nurse::query()
            ->where('clinic_id', $workItem->clinic_id)
            ->where('id', $workItem->nurse_id)
            ->first();

        if ($nurse === null) {
            return;
        }

        if ($treatment->treatment_price === null || $treatment->treatment_price_currency === null) {
            return;
        }

        $rate = $this->nurseCommissionRateResolver->resolveActive($nurse, $treatment);

        if ($rate === null) {
            return;
        }

        $currency = strtoupper((string) $treatment->treatment_price_currency);
        $priceOriginal = number_format((float) $treatment->treatment_price, 2, '.', '');
        $exchangeRate = MoneyCalculator::rateToAed($currency);
        $priceAed = MoneyCalculator::convertToAed($priceOriginal, $currency, $exchangeRate);
        $unitCommission = MoneyCalculator::percentage($priceAed, (string) $rate->commission_percentage);
        $totalCommission = MoneyCalculator::multiply($unitCommission, (int) $workItem->quantity);

        NurseCommission::query()->create([
            'clinic_id' => $workItem->clinic_id,
            'work_item_id' => $workItem->id,
            'nurse_id' => $nurse->id,
            'nurse_name_snapshot' => $nurse->name,
            'treatment_id' => $treatment->id,
            'treatment_code_snapshot' => $treatment->code,
            'treatment_name_snapshot' => $treatment->name,
            'treatment_price_original' => $priceOriginal,
            'treatment_price_currency' => $currency,
            'exchange_rate_to_aed' => $exchangeRate,
            'treatment_price_aed' => $priceAed,
            'commission_percentage' => (string) $rate->commission_percentage,
            'unit_commission_aed' => $unitCommission,
            'quantity' => (int) $workItem->quantity,
            'total_commission_aed' => $totalCommission,
        ]);
    }

    /**
     * @return Collection<int, NurseCommission>
     */
    public function commissionsForDoctorInPeriod(int $clinicId, int $doctorId, string $monthStart, string $monthEnd): Collection
    {
        return NurseCommission::query()
            ->where('clinic_id', $clinicId)
            ->whereHas('workItem.dailyWorkRow', function ($query) use ($doctorId, $monthStart, $monthEnd) {
                $query
                    ->where('doctor_id', $doctorId)
                    ->whereBetween('work_date', [$monthStart, $monthEnd]);
            })
            ->get();
    }
}
