<?php

namespace App\Services\Accounting;

use App\Models\LabPrice;
use App\Services\Audit\AuditLogService;
use Illuminate\Support\Facades\DB;

/**
 * Admin management of lab price rows — never physically delete financial configuration.
 */
class LabPriceManagementService
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
    ) {}

    /**
     * @param  array{
     *     lab_id: int,
     *     treatment_id: int,
     *     doctor_id?: int|null,
     *     unit_cost: string|float,
     *     currency?: string,
     *     valid_from?: string|null,
     *     valid_to?: string|null,
     * }  $data
     */
    public function create(array $data): LabPrice
    {
        return DB::transaction(function () use ($data) {
            $price = LabPrice::query()->create([
                'lab_id' => $data['lab_id'],
                'treatment_id' => $data['treatment_id'],
                'doctor_id' => $data['doctor_id'] ?? null,
                'unit_cost' => $data['unit_cost'],
                'currency' => $data['currency'] ?? 'AED',
                'valid_from' => $data['valid_from'] ?? null,
                'valid_to' => $data['valid_to'] ?? null,
                'is_active' => true,
            ]);

            $this->auditLogService->logLabPriceCreated($price);

            return $price->fresh(['lab', 'treatment', 'doctor']);
        });
    }

    /**
     * @param  array{
     *     unit_cost?: string|float,
     *     currency?: string,
     *     valid_from?: string|null,
     *     valid_to?: string|null,
     *     is_active?: bool,
     * }  $data
     */
    public function update(LabPrice $labPrice, array $data): LabPrice
    {
        return DB::transaction(function () use ($labPrice, $data) {
            $oldValues = $this->snapshot($labPrice);

            $labPrice->fill([
                'unit_cost' => $data['unit_cost'] ?? $labPrice->unit_cost,
                'currency' => $data['currency'] ?? $labPrice->currency,
                'valid_from' => array_key_exists('valid_from', $data) ? $data['valid_from'] : $labPrice->valid_from,
                'valid_to' => array_key_exists('valid_to', $data) ? $data['valid_to'] : $labPrice->valid_to,
            ]);

            if (array_key_exists('is_active', $data)) {
                $labPrice->is_active = (bool) $data['is_active'];
            }

            $labPrice->save();

            $this->auditLogService->logLabPriceUpdated($labPrice, $oldValues, $this->snapshot($labPrice));

            return $labPrice->fresh(['lab', 'treatment', 'doctor']);
        });
    }

    public function deactivate(LabPrice $labPrice): LabPrice
    {
        return $this->update($labPrice, ['is_active' => false]);
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
