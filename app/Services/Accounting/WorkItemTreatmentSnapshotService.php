<?php

namespace App\Services\Accounting;

use App\Models\NurseCommission;
use App\Models\Treatment;
use App\Models\WorkItem;
use App\Support\MoneyCalculator;

/**
 * Central treatment price snapshot for work items (aligned with nurse_commissions snapshot shape).
 */
class WorkItemTreatmentSnapshotService
{
    /**
     * @return array{
     *     treatment_code_snapshot: string,
     *     treatment_price_original: string|null,
     *     treatment_price_currency: string|null,
     *     exchange_rate_to_aed: string|null,
     *     treatment_price_aed: string|null,
     * }
     */
    public function buildAttributesFromTreatment(Treatment $treatment): array
    {
        $attributes = [
            'treatment_code_snapshot' => $treatment->code,
            'treatment_price_original' => null,
            'treatment_price_currency' => null,
            'exchange_rate_to_aed' => null,
            'treatment_price_aed' => null,
        ];

        if ($treatment->treatment_price === null || $treatment->treatment_price_currency === null) {
            return $attributes;
        }

        $currency = strtoupper((string) $treatment->treatment_price_currency);
        $priceOriginal = number_format((float) $treatment->treatment_price, 2, '.', '');
        $exchangeRate = MoneyCalculator::rateToAed($currency);
        $priceAed = MoneyCalculator::convertToAed($priceOriginal, $currency, $exchangeRate);

        $attributes['treatment_price_original'] = $priceOriginal;
        $attributes['treatment_price_currency'] = $currency;
        $attributes['exchange_rate_to_aed'] = $exchangeRate;
        $attributes['treatment_price_aed'] = $priceAed;

        return $attributes;
    }

    /**
     * @return array{
     *     treatment_code_snapshot: string,
     *     treatment_price_original: string,
     *     treatment_price_currency: string,
     *     exchange_rate_to_aed: string,
     *     treatment_price_aed: string,
     * }
     */
    public function buildAttributesFromNurseCommission(NurseCommission $commission): array
    {
        return [
            'treatment_code_snapshot' => $commission->treatment_code_snapshot,
            'treatment_price_original' => (string) $commission->treatment_price_original,
            'treatment_price_currency' => strtoupper((string) $commission->treatment_price_currency),
            'exchange_rate_to_aed' => (string) $commission->exchange_rate_to_aed,
            'treatment_price_aed' => (string) $commission->treatment_price_aed,
        ];
    }

    public function isSnapshotComplete(WorkItem $workItem): bool
    {
        return $workItem->treatment_code_snapshot !== null
            && $workItem->treatment_code_snapshot !== ''
            && $workItem->treatment_price_aed !== null;
    }

    public function applySnapshotFromTreatment(WorkItem $workItem, Treatment $treatment): WorkItem
    {
        $workItem->fill($this->buildAttributesFromTreatment($treatment));
        $workItem->save();

        return $workItem->fresh();
    }

    /**
     * Resolve backfill attributes without persisting. Returns null when snapshot is already complete.
     *
     * @return array<string, string|null>|null
     */
    public function resolveBackfillAttributes(WorkItem $workItem): ?array
    {
        if ($this->isSnapshotComplete($workItem)) {
            return null;
        }

        $workItem->loadMissing(['treatment', 'nurseCommission']);

        if ($workItem->nurseCommission !== null) {
            return $this->buildAttributesFromNurseCommission($workItem->nurseCommission);
        }

        if ($workItem->treatment === null) {
            return null;
        }

        return $this->buildAttributesFromTreatment($workItem->treatment);
    }
}
