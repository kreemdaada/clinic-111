<?php

namespace App\Services\Accounting;

use App\Models\Treatment;
use App\Services\Audit\AuditLogService;
use Illuminate\Support\Facades\DB;

/**
 * Admin management of treatment master data — never physically delete financial configuration.
 */
class TreatmentManagementService
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
    ) {}

    /**
     * @param  array{
     *     code: string,
     *     name: string,
     *     description?: string|null,
     *     has_lab_cost?: bool,
     * }  $data
     */
    public function create(array $data): Treatment
    {
        return DB::transaction(function () use ($data) {
            $treatment = Treatment::query()->create([
                'code' => strtoupper(trim($data['code'])),
                'name' => trim($data['name']),
                'description' => isset($data['description']) ? trim((string) $data['description']) : null,
            ]);
            $treatment->has_lab_cost = (bool) ($data['has_lab_cost'] ?? false);
            $treatment->is_active = true;
            $treatment->save();

            $this->auditLogService->logTreatmentCreated($treatment->fresh());

            return $treatment->fresh();
        });
    }

    /**
     * @param  array{
     *     code: string,
     *     name: string,
     *     description?: string|null,
     *     has_lab_cost?: bool,
     *     is_active?: bool,
     * }  $data
     */
    public function update(Treatment $treatment, array $data): Treatment
    {
        return DB::transaction(function () use ($treatment, $data) {
            $oldValues = $this->auditLogService->treatmentSnapshot($treatment);

            $treatment->fill([
                'code' => strtoupper(trim($data['code'])),
                'name' => trim($data['name']),
                'description' => array_key_exists('description', $data)
                    ? ($data['description'] !== null ? trim((string) $data['description']) : null)
                    : $treatment->description,
            ]);

            if (array_key_exists('has_lab_cost', $data)) {
                $treatment->has_lab_cost = (bool) $data['has_lab_cost'];
            }

            if (array_key_exists('is_active', $data)) {
                $treatment->is_active = (bool) $data['is_active'];
            }

            $treatment->save();

            $freshTreatment = $treatment->fresh();
            $newValues = $this->auditLogService->treatmentSnapshot($freshTreatment);
            $this->auditLogService->logTreatmentUpdated($freshTreatment, $oldValues, $newValues);

            return $freshTreatment;
        });
    }

    public function deactivate(Treatment $treatment): Treatment
    {
        return $this->update($treatment, [
            'code' => $treatment->code,
            'name' => $treatment->name,
            'description' => $treatment->description,
            'has_lab_cost' => $treatment->has_lab_cost,
            'is_active' => false,
        ]);
    }

    public function activate(Treatment $treatment): Treatment
    {
        return $this->update($treatment, [
            'code' => $treatment->code,
            'name' => $treatment->name,
            'description' => $treatment->description,
            'has_lab_cost' => $treatment->has_lab_cost,
            'is_active' => true,
        ]);
    }
}
