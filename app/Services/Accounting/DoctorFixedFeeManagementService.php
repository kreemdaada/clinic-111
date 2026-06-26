<?php

namespace App\Services\Accounting;

use App\Models\DoctorFixedFee;
use App\Services\Audit\AuditLogService;
use App\Services\Configuration\Concerns\ScopesConfigurationQueries;
use App\Services\Configuration\CurrentClinicResolver;
use App\Support\DoctorFixedFeeOverlapValidator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Admin management of doctor fixed fee rows — never physically delete financial configuration.
 */
class DoctorFixedFeeManagementService
{
    use ScopesConfigurationQueries;

    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly DoctorFixedFeeOverlapValidator $overlapValidator,
        private readonly CurrentClinicResolver $currentClinicResolver,
    ) {}

    public function listQuery(
        ?string $search = null,
        ?int $doctorId = null,
        ?int $treatmentId = null,
        string $status = 'all',
        ?string $currency = null,
    ): Builder {
        $query = $this->forCurrentClinic(DoctorFixedFee::class);

        if ($search !== null && trim($search) !== '') {
            $term = '%'.trim($search).'%';
            $query->where(function (Builder $builder) use ($term) {
                $builder
                    ->whereHas('doctor', fn (Builder $q) => $q->where('code', 'like', $term)->orWhere('name', 'like', $term))
                    ->orWhereHas('treatment', fn (Builder $q) => $q->where('code', 'like', $term)->orWhere('name', 'like', $term));
            });
        }

        if ($doctorId !== null) {
            $query->where('doctor_id', $doctorId);
        }

        if ($treatmentId !== null) {
            $query->where('treatment_id', $treatmentId);
        }

        if ($status === 'active') {
            $query->where('is_active', true);
        }

        if ($status === 'inactive') {
            $query->where('is_active', false);
        }

        if ($currency !== null && $currency !== '') {
            $query->where('currency', $currency);
        }

        return $query;
    }

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
                'clinic_id' => $this->currentClinicId(),
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
        $this->assertSameClinic($doctorFixedFee);

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
        $this->assertSameClinic($doctorFixedFee);

        return $this->update($doctorFixedFee, ['is_active' => false]);
    }

    public function activate(DoctorFixedFee $doctorFixedFee): DoctorFixedFee
    {
        $this->assertSameClinic($doctorFixedFee);

        return $this->update($doctorFixedFee, ['is_active' => true]);
    }

    public function duplicate(DoctorFixedFee $doctorFixedFee): DoctorFixedFee
    {
        $this->assertSameClinic($doctorFixedFee);

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
            $this->currentClinicId(),
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
