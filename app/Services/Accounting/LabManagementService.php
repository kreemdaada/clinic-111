<?php

namespace App\Services\Accounting;

use App\Models\Lab;
use App\Services\Audit\AuditLogService;
use App\Services\Configuration\CurrentClinicResolver;
use Illuminate\Support\Facades\DB;

/**
 * Admin management of laboratory master data — never physically delete financial configuration.
 */
class LabManagementService
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly CurrentClinicResolver $currentClinicResolver,
    ) {}

    /**
     * @param  array{name: string, code: string}  $data
     */
    public function create(array $data): Lab
    {
        return DB::transaction(function () use ($data) {
            $lab = Lab::query()->create([
                'clinic_id' => $this->currentClinicResolver->resolveId(),
                'name' => trim($data['name']),
                'code' => strtoupper(trim($data['code'])),
            ]);

            $this->auditLogService->logLabCreated($lab);

            return $lab->fresh();
        });
    }

    /**
     * @param  array{name: string, code: string, is_active?: bool}  $data
     */
    public function update(Lab $lab, array $data): Lab
    {
        return DB::transaction(function () use ($lab, $data) {
            $oldValues = $this->auditLogService->labSnapshot($lab);

            $lab->fill([
                'name' => trim($data['name']),
                'code' => strtoupper(trim($data['code'])),
            ]);

            if (array_key_exists('is_active', $data)) {
                $lab->is_active = (bool) $data['is_active'];
            }

            $lab->save();

            $freshLab = $lab->fresh();
            $newValues = $this->auditLogService->labSnapshot($freshLab);
            $this->auditLogService->logLabUpdated($freshLab, $oldValues, $newValues);

            return $freshLab;
        });
    }

    public function deactivate(Lab $lab): Lab
    {
        return $this->update($lab, [
            'name' => $lab->name,
            'code' => $lab->code,
            'is_active' => false,
        ]);
    }

    public function activate(Lab $lab): Lab
    {
        return $this->update($lab, [
            'name' => $lab->name,
            'code' => $lab->code,
            'is_active' => true,
        ]);
    }
}
