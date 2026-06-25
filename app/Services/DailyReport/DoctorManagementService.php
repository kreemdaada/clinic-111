<?php

namespace App\Services\DailyReport;

use App\Enums\CommissionType;
use App\Models\Doctor;
use App\Models\DoctorLabBilling;
use App\Models\Treatment;
use App\Services\Audit\AuditLogService;
use App\Support\LabCostTreatmentCatalog;
use Illuminate\Support\Facades\DB;

/**
 * Create and update doctors from the V2 UI — never physically delete financial configuration.
 */
class DoctorManagementService
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
    ) {}

    /**
     * @param  array{
     *     name: string,
     *     code: string,
     *     commission_type: string,
     *     commission_percentage?: float|string|null,
     *     default_lab_id?: int|null,
     *     seed_full_lab_billing?: bool,
     * }  $data
     */
    public function create(array $data): Doctor
    {
        return DB::transaction(function () use ($data) {
            $doctor = Doctor::query()->create([
                'name' => $data['name'],
                'code' => strtoupper(trim($data['code'])),
                'commission_type' => $data['commission_type'],
                'commission_percentage' => $data['commission_type'] === CommissionType::Percentage->value
                    ? $data['commission_percentage'] ?? null
                    : null,
                'default_lab_id' => $data['default_lab_id'] ?? null,
                'is_active' => true,
            ]);

            if (
                $doctor->commission_type === CommissionType::Percentage
                && ($data['seed_full_lab_billing'] ?? true)
            ) {
                $this->seedFullLabBilling($doctor);
            }

            $this->auditLogService->logDoctorCreated($doctor);

            return $doctor->fresh('defaultLab');
        });
    }

    /**
     * @param  array{
     *     name: string,
     *     commission_type: string,
     *     commission_percentage?: float|string|null,
     *     default_lab_id?: int|null,
     *     is_active?: bool,
     * }  $data
     */
    public function update(Doctor $doctor, array $data): Doctor
    {
        return DB::transaction(function () use ($doctor, $data) {
            $oldValues = $this->auditLogService->doctorSnapshot($doctor);

            $doctor->fill([
                'name' => $data['name'],
                'commission_type' => $data['commission_type'],
                'commission_percentage' => $data['commission_type'] === CommissionType::Percentage->value
                    ? $data['commission_percentage'] ?? null
                    : null,
                'default_lab_id' => $data['default_lab_id'] ?? null,
            ]);

            if (array_key_exists('is_active', $data)) {
                $doctor->is_active = (bool) $data['is_active'];
            }

            $doctor->save();

            $newValues = $this->auditLogService->doctorSnapshot($doctor->fresh());
            $this->auditLogService->logDoctorUpdated($doctor, $oldValues, $newValues);

            return $doctor->fresh('defaultLab');
        });
    }

    /**
     * Soft-deactivate a doctor — financial configuration is never physically deleted.
     */
    public function deactivate(Doctor $doctor): Doctor
    {
        return DB::transaction(function () use ($doctor) {
            $oldValues = $this->auditLogService->doctorSnapshot($doctor);

            $doctor->update(['is_active' => false]);

            $this->auditLogService->logDoctorDeactivated($doctor->fresh(), $oldValues);

            return $doctor->fresh('defaultLab');
        });
    }

    private function seedFullLabBilling(Doctor $doctor): void
    {
        foreach (LabCostTreatmentCatalog::codes() as $treatmentCode) {
            $treatment = Treatment::query()->where('code', $treatmentCode)->first();

            if ($treatment === null) {
                continue;
            }

            DoctorLabBilling::query()->updateOrCreate(
                [
                    'doctor_id' => $doctor->id,
                    'treatment_id' => $treatment->id,
                ],
                [
                    'bill_lab_job' => true,
                ],
            );
        }
    }
}
