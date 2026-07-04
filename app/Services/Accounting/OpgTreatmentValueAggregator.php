<?php

namespace App\Services\Accounting;

use App\Support\MoneyCalculator;
use App\Support\OpgTreatmentCodes;
use Illuminate\Support\Collection;

/**
 * Aggregates informative OPG treatment values from nurse commission snapshots.
 *
 * Value = persisted treatment_price_aed × quantity. Callers retain query scope,
 * status filters, and currency conversion.
 */
final class OpgTreatmentValueAggregator
{
    /**
     * Sum treatment_price_aed × quantity for commissions matching a canonical OPG code.
     *
     * @param  Collection<int, object{treatment_code_snapshot: string, treatment_price_aed: mixed, quantity: int}>  $commissions
     */
    public function sumForCanonicalCode(Collection $commissions, string $canonicalTreatmentCode): string
    {
        $total = '0.00';

        foreach ($commissions as $commission) {
            if (! OpgTreatmentCodes::matches($commission->treatment_code_snapshot, $canonicalTreatmentCode)) {
                continue;
            }

            $total = MoneyCalculator::add($total, $this->lineValueAed($commission));
        }

        return $total;
    }

    /**
     * @param  object{treatment_price_aed: mixed, quantity: int}  $commission
     */
    public function lineValueAed(object $commission): string
    {
        return MoneyCalculator::multiply(
            (string) $commission->treatment_price_aed,
            (int) $commission->quantity,
        );
    }
}
