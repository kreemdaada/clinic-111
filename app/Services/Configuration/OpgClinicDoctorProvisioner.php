<?php

namespace App\Services\Configuration;

use App\Enums\CommissionType;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\Lab;
use App\Support\OpgClinicDoctor;

/**
 * Idempotent clinic-level OPG doctor used for imported OPG sections.
 */
class OpgClinicDoctorProvisioner
{
    public function provisionForClinic(Clinic $clinic): Doctor
    {
        $lab = Lab::query()
            ->where('clinic_id', $clinic->id)
            ->where('is_active', true)
            ->orderBy('id')
            ->first();

        return Doctor::query()->updateOrCreate(
            [
                'clinic_id' => $clinic->id,
                'code' => OpgClinicDoctor::CODE,
            ],
            [
                'name' => OpgClinicDoctor::NAME,
                'commission_type' => CommissionType::Percentage,
                'commission_percentage' => 0,
                'default_lab_id' => $lab?->id,
                'is_active' => true,
            ],
        );
    }
}
