<?php

namespace App\Services\Configuration;

use App\Models\Doctor;
use App\Models\Lab;
use App\Models\Treatment;
use App\Services\Configuration\Concerns\ScopesConfigurationQueries;
use Illuminate\Support\Collection;

/**
 * Clinic-scoped reference data for API clients (ADR-028).
 */
class ReferenceDataService
{
    use ScopesConfigurationQueries;

    public function __construct(
        private readonly CurrentClinicResolver $currentClinicResolver,
    ) {}

    /**
     * @return Collection<int, Doctor>
     */
    public function activeDoctors(): Collection
    {
        return $this->forCurrentClinic(Doctor::class)
            ->with('defaultLab')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    /**
     * @return Collection<int, Treatment>
     */
    public function activeTreatments(): Collection
    {
        return $this->forCurrentClinic(Treatment::class)
            ->where('is_active', true)
            ->orderBy('code')
            ->get();
    }

    /**
     * @return Collection<int, Lab>
     */
    public function activeLabs(): Collection
    {
        return $this->forCurrentClinic(Lab::class)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }
}
