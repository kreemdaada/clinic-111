<?php

namespace App\Services\Accounting;

use App\Models\LabPrice;
use App\Services\Audit\AuditLogService;
use App\Services\Configuration\Concerns\ScopesConfigurationQueries;
use App\Services\Configuration\CurrentClinicResolver;
use App\Support\LabPriceOverlapValidator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Admin management of lab price rows — never physically delete financial configuration.
 */
class LabPriceManagementService
{
    use ScopesConfigurationQueries;

    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly LabPriceOverlapValidator $overlapValidator,
        private readonly CurrentClinicResolver $currentClinicResolver,
    ) {}

    public function listQuery(
        ?string $search = null,
        ?int $labId = null,
        ?int $treatmentId = null,
        string $doctorFilter = 'all',
        string $status = 'all',
        ?string $currency = null,
    ): Builder {
        $query = $this->forCurrentClinic(LabPrice::class);

        if ($search !== null && trim($search) !== '') {
            $term = '%'.trim($search).'%';
            $query->where(function (Builder $builder) use ($term) {
                $builder
                    ->whereHas('lab', fn (Builder $q) => $q->where('code', 'like', $term)->orWhere('name', 'like', $term))
                    ->orWhereHas('treatment', fn (Builder $q) => $q->where('code', 'like', $term)->orWhere('name', 'like', $term))
                    ->orWhereHas('doctor', fn (Builder $q) => $q->where('code', 'like', $term)->orWhere('name', 'like', $term));
            });
        }

        if ($labId !== null) {
            $query->where('lab_id', $labId);
        }

        if ($treatmentId !== null) {
            $query->where('treatment_id', $treatmentId);
        }

        if ($doctorFilter === 'general') {
            $query->whereNull('doctor_id');
        } elseif ($doctorFilter !== 'all' && $doctorFilter !== '') {
            $query->where('doctor_id', (int) $doctorFilter);
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
     *     lab_id: int,
     *     treatment_id: int,
     *     doctor_id?: int|null,
     *     unit_cost: string|float,
     *     currency?: string,
     *     valid_from?: string|null,
     *     valid_to?: string|null,
     *     is_active?: bool,
     * }  $data
     */
    public function create(array $data): LabPrice
    {
        return DB::transaction(function () use ($data) {
            $doctorId = $data['doctor_id'] ?? null;
            $validFrom = $data['valid_from'] ?? null;
            $validTo = $data['valid_to'] ?? null;
            $isActive = (bool) ($data['is_active'] ?? true);

            if ($isActive) {
                $this->assertNoOverlap(
                    (int) $data['lab_id'],
                    (int) $data['treatment_id'],
                    $doctorId,
                    $validFrom,
                    $validTo,
                );
            }

            $price = LabPrice::query()->create([
                'clinic_id' => $this->currentClinicId(),
                'lab_id' => $data['lab_id'],
                'treatment_id' => $data['treatment_id'],
                'doctor_id' => $doctorId,
                'unit_cost' => $data['unit_cost'],
                'currency' => strtoupper($data['currency'] ?? $this->currentClinicResolver->resolve()->currency ?? 'AED'),
                'valid_from' => $validFrom,
                'valid_to' => $validTo,
            ]);
            $price->is_active = $isActive;
            $price->save();

            $this->auditLogService->logLabPriceCreated($price->fresh(['lab', 'treatment', 'doctor']));

            return $price->fresh(['lab', 'treatment', 'doctor']);
        });
    }

    /**
     * @param  array{
     *     lab_id?: int,
     *     treatment_id?: int,
     *     doctor_id?: int|null,
     *     unit_cost?: string|float,
     *     currency?: string,
     *     valid_from?: string|null,
     *     valid_to?: string|null,
     *     is_active?: bool,
     * }  $data
     */
    public function update(LabPrice $labPrice, array $data): LabPrice
    {
        $this->assertSameClinic($labPrice);

        return DB::transaction(function () use ($labPrice, $data) {
            $oldValues = $this->snapshot($labPrice);

            $labId = (int) ($data['lab_id'] ?? $labPrice->lab_id);
            $treatmentId = (int) ($data['treatment_id'] ?? $labPrice->treatment_id);
            $doctorId = array_key_exists('doctor_id', $data) ? $data['doctor_id'] : $labPrice->doctor_id;
            $validFrom = array_key_exists('valid_from', $data) ? $data['valid_from'] : $labPrice->valid_from?->toDateString();
            $validTo = array_key_exists('valid_to', $data) ? $data['valid_to'] : $labPrice->valid_to?->toDateString();
            $willBeActive = array_key_exists('is_active', $data) ? (bool) $data['is_active'] : $labPrice->is_active;

            if ($willBeActive) {
                $this->assertNoOverlap($labId, $treatmentId, $doctorId, $validFrom, $validTo, $labPrice->id);
            }

            $labPrice->fill([
                'lab_id' => $labId,
                'treatment_id' => $treatmentId,
                'doctor_id' => $doctorId,
                'unit_cost' => $data['unit_cost'] ?? $labPrice->unit_cost,
                'currency' => isset($data['currency']) ? strtoupper($data['currency']) : $labPrice->currency,
                'valid_from' => $validFrom,
                'valid_to' => $validTo,
            ]);

            if (array_key_exists('is_active', $data)) {
                $labPrice->is_active = (bool) $data['is_active'];
            }

            $labPrice->save();

            $freshPrice = $labPrice->fresh(['lab', 'treatment', 'doctor']);
            $newValues = $this->snapshot($freshPrice);

            if (! ($oldValues['is_active'] ?? true) && ($newValues['is_active'] ?? false)) {
                $this->auditLogService->logLabPriceActivated($freshPrice, $oldValues);
            } else {
                $this->auditLogService->logLabPriceUpdated($freshPrice, $oldValues, $newValues);
            }

            return $freshPrice;
        });
    }

    public function deactivate(LabPrice $labPrice): LabPrice
    {
        $this->assertSameClinic($labPrice);

        return $this->update($labPrice, ['is_active' => false]);
    }

    public function activate(LabPrice $labPrice): LabPrice
    {
        $this->assertSameClinic($labPrice);

        return $this->update($labPrice, ['is_active' => true]);
    }

    public function duplicate(LabPrice $labPrice): LabPrice
    {
        $this->assertSameClinic($labPrice);

        return $this->create([
            'lab_id' => $labPrice->lab_id,
            'treatment_id' => $labPrice->treatment_id,
            'doctor_id' => $labPrice->doctor_id,
            'unit_cost' => $labPrice->unit_cost,
            'currency' => $labPrice->currency,
            'valid_from' => $labPrice->valid_from?->toDateString(),
            'valid_to' => $labPrice->valid_to?->toDateString(),
            'is_active' => false,
        ]);
    }

    private function assertNoOverlap(
        int $labId,
        int $treatmentId,
        ?int $doctorId,
        ?string $validFrom,
        ?string $validTo,
        ?int $excludeLabPriceId = null,
    ): void {
        if ($this->overlapValidator->hasActiveOverlap(
            $this->currentClinicId(),
            $labId,
            $treatmentId,
            $doctorId,
            $validFrom,
            $validTo,
            $excludeLabPriceId,
        )) {
            throw ValidationException::withMessages([
                'lab_id' => 'An active price already exists for this lab, treatment, doctor override, and validity period.',
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(LabPrice $labPrice): array
    {
        return [
            'lab_id' => $labPrice->lab_id,
            'treatment_id' => $labPrice->treatment_id,
            'doctor_id' => $labPrice->doctor_id,
            'unit_cost' => (string) $labPrice->unit_cost,
            'currency' => $labPrice->currency,
            'valid_from' => $labPrice->valid_from?->toDateString(),
            'valid_to' => $labPrice->valid_to?->toDateString(),
            'is_active' => $labPrice->is_active,
        ];
    }
}
