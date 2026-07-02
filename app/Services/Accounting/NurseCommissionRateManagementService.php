<?php

namespace App\Services\Accounting;

use App\Models\Nurse;
use App\Models\NurseCommissionRate;
use App\Models\Treatment;
use App\Services\Audit\AuditLogService;
use App\Services\Configuration\Concerns\ScopesConfigurationQueries;
use App\Services\Configuration\CurrentClinicResolver;
use App\Services\Configuration\TenantResourceGuard;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Admin management of nurse commission rates — never physically delete financial configuration.
 */
class NurseCommissionRateManagementService
{
    use ScopesConfigurationQueries;

    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly CurrentClinicResolver $currentClinicResolver,
        private readonly TenantResourceGuard $tenantResourceGuard,
    ) {}

    public function listForTreatment(Treatment $treatment): Builder
    {
        $this->assertSameClinic($treatment);

        return NurseCommissionRate::query()
            ->where('clinic_id', $treatment->clinic_id)
            ->where('treatment_id', $treatment->id)
            ->with('nurse')
            ->orderByDesc('is_active')
            ->orderBy('nurse_id');
    }

    public function listForNurse(Nurse $nurse): Builder
    {
        $this->assertSameClinic($nurse);

        return NurseCommissionRate::query()
            ->where('clinic_id', $nurse->clinic_id)
            ->where('nurse_id', $nurse->id)
            ->with('treatment')
            ->orderByDesc('is_active')
            ->orderBy('treatment_id');
    }

    /**
     * @param  array{treatment_id: int, commission_percentage: string|float, is_active?: bool}  $data
     */
    public function createForNurse(Nurse $nurse, array $data): NurseCommissionRate
    {
        $this->assertSameClinic($nurse);

        /** @var Treatment $treatment */
        $treatment = $this->tenantResourceGuard->findAccessibleOrAbort(Treatment::class, (int) $data['treatment_id']);

        if (! $nurse->is_active) {
            throw ValidationException::withMessages([
                'nurse_id' => 'The nurse must be active.',
            ]);
        }

        return $this->createForTreatment($treatment, [
            'nurse_id' => $nurse->id,
            'commission_percentage' => $data['commission_percentage'],
            'is_active' => $data['is_active'] ?? true,
        ], 'treatment_id');
    }

    /**
     * @param  array{nurse_id: int, commission_percentage: string|float, is_active?: bool}  $data
     */
    public function createForTreatment(Treatment $treatment, array $data, string $duplicateErrorField = 'nurse_id'): NurseCommissionRate
    {
        $this->assertSameClinic($treatment);
        $this->assertTreatmentEligible($treatment);
        $this->assertRelatedResourcesAccessible($treatment, (int) $data['nurse_id']);

        return DB::transaction(function () use ($treatment, $data, $duplicateErrorField) {
            $isActive = (bool) ($data['is_active'] ?? true);

            if ($isActive) {
                $this->assertNoActiveDuplicate((int) $data['nurse_id'], $treatment->id, null, $duplicateErrorField);
            }

            $rate = NurseCommissionRate::query()->create([
                'clinic_id' => $treatment->clinic_id,
                'nurse_id' => $data['nurse_id'],
                'treatment_id' => $treatment->id,
                'commission_percentage' => $this->normalizePercentage($data['commission_percentage']),
            ]);
            $rate->is_active = $isActive;
            $rate->save();

            $this->auditLogService->logNurseCommissionRateCreated($rate->fresh(['nurse', 'treatment']));

            return $rate->fresh(['nurse', 'treatment']);
        });
    }

    /**
     * @param  array{nurse_id?: int, commission_percentage?: string|float, is_active?: bool}  $data
     */
    public function update(NurseCommissionRate $rate, array $data): NurseCommissionRate
    {
        $this->assertSameClinic($rate);

        return DB::transaction(function () use ($rate, $data) {
            $oldValues = $this->auditLogService->nurseCommissionRateSnapshot($rate);

            $nurseId = (int) ($data['nurse_id'] ?? $rate->nurse_id);
            $treatment = $rate->treatment()->firstOrFail();

            $this->assertTreatmentEligible($treatment);
            $this->assertRelatedResourcesAccessible($treatment, $nurseId);

            $willBeActive = array_key_exists('is_active', $data) ? (bool) $data['is_active'] : $rate->is_active;

            if ($willBeActive) {
                $this->assertNoActiveDuplicate($nurseId, $treatment->id, $rate->id, 'treatment_id');
            }

            $rate->fill([
                'nurse_id' => $nurseId,
                'commission_percentage' => array_key_exists('commission_percentage', $data)
                    ? $this->normalizePercentage($data['commission_percentage'])
                    : $rate->commission_percentage,
            ]);

            if (array_key_exists('is_active', $data)) {
                $rate->is_active = (bool) $data['is_active'];
            }

            $rate->save();

            $fresh = $rate->fresh(['nurse', 'treatment']);
            $newValues = $this->auditLogService->nurseCommissionRateSnapshot($fresh);

            if (! ($oldValues['is_active'] ?? true) && ($newValues['is_active'] ?? false)) {
                $this->auditLogService->logNurseCommissionRateActivated($fresh, $oldValues);
            } elseif (($oldValues['is_active'] ?? true) && ! ($newValues['is_active'] ?? false)) {
                $this->auditLogService->logNurseCommissionRateDeactivated($fresh, $oldValues);
            } else {
                $this->auditLogService->logNurseCommissionRateUpdated($fresh, $oldValues, $newValues);
            }

            return $fresh;
        });
    }

    public function deactivate(NurseCommissionRate $rate): NurseCommissionRate
    {
        return $this->update($rate, ['is_active' => false]);
    }

    public function activate(NurseCommissionRate $rate): NurseCommissionRate
    {
        return $this->update($rate, ['is_active' => true]);
    }

    private function assertTreatmentEligible(Treatment $treatment): void
    {
        if (! $treatment->requires_nurse_commission) {
            throw ValidationException::withMessages([
                'treatment_id' => 'Nurse commission rates can only be configured for treatments that require nurse commission.',
            ]);
        }

        if (! $treatment->is_active) {
            throw ValidationException::withMessages([
                'treatment_id' => 'The treatment must be active.',
            ]);
        }
    }

    private function assertRelatedResourcesAccessible(Treatment $treatment, int $nurseId): void
    {
        $this->tenantResourceGuard->findAccessibleOrAbort(Treatment::class, $treatment->id);

        $nurse = $this->tenantResourceGuard->findAccessibleOrAbort(Nurse::class, $nurseId);

        if (! $nurse->is_active) {
            throw ValidationException::withMessages([
                'nurse_id' => 'The selected nurse must be active.',
            ]);
        }
    }

    private function assertNoActiveDuplicate(int $nurseId, int $treatmentId, ?int $excludeId = null, string $errorField = 'nurse_id'): void
    {
        $query = NurseCommissionRate::query()
            ->where('clinic_id', $this->currentClinicId())
            ->where('nurse_id', $nurseId)
            ->where('treatment_id', $treatmentId)
            ->where('is_active', true)
            ->lockForUpdate();

        if ($excludeId !== null) {
            $query->where('id', '!=', $excludeId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                $errorField => 'An active commission rate already exists for this nurse and treatment.',
            ]);
        }
    }

    private function normalizePercentage(string|float $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }
}
