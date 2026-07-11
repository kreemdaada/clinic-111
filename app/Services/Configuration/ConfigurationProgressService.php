<?php

namespace App\Services\Configuration;

use App\Enums\CommissionType;
use App\Models\Doctor;
use App\Models\DoctorFixedFee;
use App\Models\Lab;
use App\Models\LabPrice;
use App\Models\Treatment;
use App\Services\Configuration\Concerns\ScopesConfigurationQueries;

/**
 * Calculates guided business-configuration progress for the current clinic (ADR-031).
 */
class ConfigurationProgressService
{
    use ScopesConfigurationQueries;

    public const STEP_DOCTORS = 'doctors';

    public const STEP_LABS = 'labs';

    public const STEP_TREATMENTS = 'treatments';

    public const STEP_LAB_PRICES = 'lab_prices';

    public const STEP_DOCTOR_FIXED_FEES = 'doctor_fixed_fees';

    public const STEP_IMPORT = 'import';

    public function __construct(
        private readonly CurrentClinicResolver $currentClinicResolver,
    ) {}

    public function readyForImport(): bool
    {
        return $this->status()['ready_for_import'];
    }

    /**
     * @return list<string>
     */
    public function missingModules(): array
    {
        return $this->status()['missing_modules'];
    }

    /**
     * @return array{
     *     steps: list<array<string, mixed>>,
     *     missing_modules: list<string>,
     *     progress_percentage: int,
     *     current_step: string|null,
     *     ready_for_import: bool,
     * }
     */
    public function status(): array
    {
        $fixedFeesRequired = $this->fixedFeesRequired();

        $steps = [
            $this->buildStep(
                key: self::STEP_DOCTORS,
                label: __('dashboard.steps.doctors.label'),
                completed: $this->hasActiveDoctors(),
                required: true,
                indexRoute: 'doctors.index',
                description: __('dashboard.steps.doctors.description'),
            ),
            $this->buildStep(
                key: self::STEP_LABS,
                label: __('dashboard.steps.labs.label'),
                completed: $this->hasActiveLabs(),
                required: true,
                indexRoute: 'labs.index',
                description: __('dashboard.steps.labs.description'),
            ),
            $this->buildStep(
                key: self::STEP_TREATMENTS,
                label: __('dashboard.steps.treatments.label'),
                completed: $this->hasActiveTreatments(),
                required: true,
                indexRoute: 'treatments.index',
                description: __('dashboard.steps.treatments.description'),
            ),
            $this->buildStep(
                key: self::STEP_LAB_PRICES,
                label: __('dashboard.steps.lab_prices.label'),
                completed: $this->hasActiveLabPrices(),
                required: true,
                indexRoute: 'lab-prices.index',
                description: __('dashboard.steps.lab_prices.description'),
            ),
            $this->buildStep(
                key: self::STEP_DOCTOR_FIXED_FEES,
                label: __('dashboard.steps.doctor_fixed_fees.label'),
                completed: $this->fixedFeesComplete(),
                required: $fixedFeesRequired,
                indexRoute: 'doctor-fixed-fees.index',
                description: $fixedFeesRequired
                    ? __('dashboard.steps.doctor_fixed_fees.description_required')
                    : __('dashboard.steps.doctor_fixed_fees.description_optional'),
            ),
            $this->buildStep(
                key: self::STEP_IMPORT,
                label: __('dashboard.steps.import.label'),
                completed: false,
                required: false,
                indexRoute: 'imports.index',
                description: __('dashboard.steps.import.description'),
                optionalCompletion: true,
            ),
        ];

        $requiredSteps = collect($steps)->where('required', true);
        $completedRequired = $requiredSteps->where('completed', true)->count();
        $totalRequired = $requiredSteps->count();

        $progressPercentage = $totalRequired === 0
            ? 100
            : (int) round(($completedRequired / $totalRequired) * 100);

        $readyForImport = $completedRequired === $totalRequired;

        if ($readyForImport) {
            $steps = array_map(function (array $step) {
                if ($step['key'] === self::STEP_IMPORT) {
                    $step['completed'] = true;
                }

                return $step;
            }, $steps);
        }

        $missingModules = $requiredSteps
            ->reject(fn (array $step) => $step['completed'])
            ->pluck('key')
            ->values()
            ->all();

        $currentStep = collect($steps)
            ->first(fn (array $step) => $step['required'] && ! $step['completed']);

        return [
            'steps' => $steps,
            'missing_modules' => $missingModules,
            'progress_percentage' => $progressPercentage,
            'current_step' => $currentStep['key'] ?? ($readyForImport ? self::STEP_IMPORT : null),
            'ready_for_import' => $readyForImport,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildStep(
        string $key,
        string $label,
        bool $completed,
        bool $required,
        string $indexRoute,
        string $description,
        bool $optionalCompletion = false,
    ): array {
        return [
            'key' => $key,
            'label' => $label,
            'completed' => $completed,
            'required' => $required,
            'index_route' => $indexRoute,
            'description' => $description,
            'optional_completion' => $optionalCompletion,
        ];
    }

    private function hasActiveDoctors(): bool
    {
        return $this->forCurrentClinic(Doctor::class)->where('is_active', true)->exists();
    }

    private function hasActiveLabs(): bool
    {
        return $this->forCurrentClinic(Lab::class)->where('is_active', true)->exists();
    }

    private function hasActiveTreatments(): bool
    {
        return $this->forCurrentClinic(Treatment::class)->where('is_active', true)->exists();
    }

    private function hasActiveLabPrices(): bool
    {
        return $this->forCurrentClinic(LabPrice::class)->where('is_active', true)->exists();
    }

    private function fixedFeesRequired(): bool
    {
        return $this->forCurrentClinic(Doctor::class)
            ->where('is_active', true)
            ->where('commission_type', CommissionType::Fixed)
            ->exists();
    }

    private function fixedFeesComplete(): bool
    {
        if (! $this->fixedFeesRequired()) {
            return true;
        }

        if ($this->forCurrentClinic(DoctorFixedFee::class)->where('is_active', true)->count() === 0) {
            return false;
        }

        $fixedDoctorsMissingFees = $this->forCurrentClinic(Doctor::class)
            ->where('is_active', true)
            ->where('commission_type', CommissionType::Fixed)
            ->whereDoesntHave('doctorFixedFees', fn ($query) => $query->where('is_active', true))
            ->exists();

        return ! $fixedDoctorsMissingFees;
    }
}
