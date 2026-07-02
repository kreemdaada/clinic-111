<?php

namespace App\Services\Accounting;

use App\Models\Nurse;
use App\Models\NurseCommissionRate;
use App\Models\Treatment;

/**
 * Resolves the active nurse commission rate for nurse + treatment.
 */
class NurseCommissionRateResolver
{
    public function resolveActive(Nurse $nurse, Treatment $treatment): ?NurseCommissionRate
    {
        if ((int) $nurse->clinic_id !== (int) $treatment->clinic_id) {
            return null;
        }

        return NurseCommissionRate::query()
            ->where('clinic_id', $nurse->clinic_id)
            ->where('nurse_id', $nurse->id)
            ->where('treatment_id', $treatment->id)
            ->where('is_active', true)
            ->first();
    }
}
