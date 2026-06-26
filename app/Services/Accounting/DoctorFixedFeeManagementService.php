<?php

namespace App\Services\Accounting;

use App\Models\DoctorFixedFee;
use App\Services\Audit\AuditLogService;
use App\Support\DoctorFixedFeeOverlapValidator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Admin management of doctor fixed fee rows — never physically delete financial configuration.
 */
class DoctorFixedFeeManagementService
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly DoctorFixedFeeOverlapValidator $overlapValidator,
    ) {}

    /**
     * @param  array{
     *     doctor_id: int,
     *     treatment_id: int,
     *     fee_amount: string|float,
     *     currency?: string,
     *     valid_from?: string|null,
     *     valid_to?: string|null,
     *     is_active?: bool,
     * }  $data
     */
    public function create(array $data): DoctorFixedFee
    {
        return DB::transaction(function () use ($data) {
            $validFrom = $data['valid_from'] ?? null;
            $validTo = $data['valid_to'] ?? null;
            $isActive = (bool) ($data['is_active'] ?? true);

            if ($isActive) {
                $this->assertNoOverlap(
                    (int) $data['doctor_id'],
                    (int) $data['treatment_id'],
                    $validFrom,
                    $validTo,
                );
            }

            $fee = DoctorFixedFee::query()->create([
                'doctor_id' => $data['doctor_id'],
                'treatment_id' => $data['treatment_id'],
                'fee_amount' => $data['fee_amount'],
                'currency' => strtoupper($data['currency'] ?? 'AED'),
                'valid_from' => $validFrom,
                'valid_to' => $validTo,
            ]);
            $fee->is_active = $isActive;
            $fee->save();

            $this->auditLogService->logDoctorFixedFeeCreated($fee->fresh(['doctor', 'treatment']));

            return $fee->fresh(['doctor', 'treatment']);
        });
    }

    /**
     * @param  array{
     *     doctor_id?: int,
     *     treatment_id?: int,
     *     fee_amount?: string|float,
     *     currency?: string,
     *     valid_from?: string|null,
     *     valid_to?: string|null,
     *     is_active?: bool,
     * }  $data
     */
    public function update(DoctorFixedFee $doctorFixedFee, array $data): DoctorFixedFee
    {
        return DB::transaction(function () use ($doctorFixedFee, $data) {
            $oldValues = $this->snapshot($doctorFixedFee);

            $doctorId = (int) ($data['doctor_id'] ?? $doctorFixedFee->doctor_id);
            $treatmentId = (int) ($data['treatment_id'] ?? $doctorFixedFee->treatment_id);
            $validFrom = array_key_exists('valid_from', $data) ? $data['valid_from'] : $doctorFixedFee->valid_from?->toDateString();
            $validTo = array_key_exists('valid_to', $data) ? $data['valid_to'] : $doctorFixedFee->valid_to?->toDateString();
            $willBeActive = array_key_exists('is_active', $data) ? (bool) $data['is_active'] : $doctorFixedFee->is_active;

            if ($willBeActive) {
                $this->assertNoOverlap($doctorId, $treatmentId, $validFrom, $validTo, $doctorFixedFee->id);
            }

            $doctorFixedFee->fill([
                'doctor_id' => $doctorId,
                'treatment_id' => $treatmentId,
                'fee_amount' => $data['fee_amount'] ?? $doctorFixedFee->fee_amount,
                'currency' => isset($data['currency']) ? strtoupper($data['currency']) : $doctorFixedFee->currency,
                'valid_from' => $validFrom,
                'valid_to' => $validTo,
            ]);

            if (array_key_exists('is_active', $data)) {
                $doctorFixedFee->is_active = (bool) $data['is_active'];
            }

            $doctorFixedFee->save();

            $freshFee = $doctorFixedFee->fresh(['doctor', 'treatment']);
            $newValues = $this->snapshot($freshFee);

            if (! ($oldValues['is_active'] ?? true) && ($newValues['is_active'] ?? false)) {
                $this->auditLogService->logDoctorFixedFeeActivated($freshFee, $oldValues);
            } else {
                $this->auditLogService->logDoctorFixedFeeUpdated($freshFee, $oldValues, $newValues);
            }

            return $freshFee;
        });
    }

    public function deactivate(DoctorFixedFee $doctorFixedFee): DoctorFixedFee
    {
        return $this->update($doctorFixedFee, ['is_active' => false]);
    }

    public function activate(DoctorFixedFee $doctorFixedFee): DoctorFixedFee
    {
        return $this->update($doctorFixedFee, ['is_active' => true]);
    }

    public function duplicate(DoctorFixedFee $doctorFixedFee): DoctorFixedFee
    {
        return $this->create([
            'doctor_id' => $doctorFixedFee->doctor_id,
            'treatment_id' => $doctorFixedFee->treatment_id,
            'fee_amount' => $doctorFixedFee->fee_amount,
            'currency' => $doctorFixedFee->currency,
            'valid_from' => $doctorFixedFee->valid_from?->toDateString(),
            'valid_to' => $doctorFixedFee->valid_to?->toDateString(),
            'is_active' => false,
        ]);
    }

    private function assertNoOverlap(
        int $doctorId,
        int $treatmentId,
        ?string $validFrom,
        ?string $validTo,
        ?int $excludeDoctorFixedFeeId = null,
    ): void {
        if ($this->overlapValidator->hasActiveOverlap(
            $doctorId,
            $treatmentId,
            $validFrom,
            $validTo,
            $excludeDoctorFixedFeeId,
        )) {
            throw ValidationException::withMessages([
                'doctor_id' => 'An active fee rule already exists for this doctor, treatment, and validity period.',
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(DoctorFixedFee $doctorFixedFee): array
    {
        return [
            'doctor_id' => $doctorFixedFee->doctor_id,
            'treatment_id' => $doctorFixedFee->treatment_id,
            'fee_amount' => (string) $doctorFixedFee->fee_amount,
            'currency' => $doctorFixedFee->currency,
            'valid_from' => $doctorFixedFee->valid_from?->toDateString(),
            'valid_to' => $doctorFixedFee->valid_to?->toDateString(),
            'is_active' => $doctorFixedFee->is_active,
        ];
    }
}
