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
                label: 'Doctors',
                completed: $this->hasActiveDoctors(),
                required: true,
                indexRoute: 'doctors.index',
                description: 'Add at least one active doctor.',
            ),
            $this->buildStep(
                key: self::STEP_LABS,
                label: 'Laboratories',
                completed: $this->hasActiveLabs(),
                required: true,
                indexRoute: 'labs.index',
                description: 'Keep at least one active laboratory.',
            ),
            $this->buildStep(
                key: self::STEP_TREATMENTS,
                label: 'Treatments',
                completed: $this->hasActiveTreatments(),
                required: true,
                indexRoute: 'treatments.index',
                description: 'Add at least one active treatment code.',
            ),
            $this->buildStep(
                key: self::STEP_LAB_PRICES,
                label: 'Lab Prices',
                completed: $this->hasActiveLabPrices(),
                required: true,
                indexRoute: 'lab-prices.index',
                description: 'Add at least one active lab price.',
            ),
            $this->buildStep(
                key: self::STEP_DOCTOR_FIXED_FEES,
                label: 'Doctor Fixed Fees',
                completed: $this->fixedFeesComplete(),
                required: $fixedFeesRequired,
                indexRoute: 'doctor-fixed-fees.index',
                description: $fixedFeesRequired
                    ? 'Configure fee rules for no-commission doctors.'
                    : 'Optional unless you use no-commission doctors.',
            ),
            $this->buildStep(
                key: self::STEP_IMPORT,
                label: 'Import Report',
                completed: false,
                required: false,
                indexRoute: 'imports.index',
                description: 'Import your first daily Excel report.',
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
            $steps = array_map(function (array $step) use ($readyForImport) {
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
