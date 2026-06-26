<?php

namespace App\Services\Configuration;

use App\Models\Clinic;
use App\Services\Audit\AuditLogService;
use Illuminate\Support\Facades\DB;

/**
 * Admin management of clinic tenant records — never physically delete tenant roots.
 */
class ClinicManagementService
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
    ) {}

    /**
     * @param  array{
     *     name: string,
     *     code: string,
     *     currency: string,
     *     timezone: string,
     *     country: string,
     * }  $data
     */
    public function create(array $data): Clinic
    {
        return DB::transaction(function () use ($data) {
            $clinic = Clinic::query()->create([
                'name' => trim($data['name']),
                'code' => strtoupper(trim($data['code'])),
                'currency' => strtoupper(trim($data['currency'])),
                'timezone' => trim($data['timezone']),
                'country' => trim($data['country']),
            ]);

            $this->auditLogService->logClinicCreated($clinic);

            return $clinic->fresh();
        });
    }

    /**
     * @param  array{
     *     name: string,
     *     code: string,
     *     currency: string,
     *     timezone: string,
     *     country: string,
     *     is_active?: bool,
     * }  $data
     */
    public function update(Clinic $clinic, array $data): Clinic
    {
        return DB::transaction(function () use ($clinic, $data) {
            $oldValues = $this->auditLogService->clinicSnapshot($clinic);

            $clinic->fill([
                'name' => trim($data['name']),
                'code' => strtoupper(trim($data['code'])),
                'currency' => strtoupper(trim($data['currency'])),
                'timezone' => trim($data['timezone']),
                'country' => trim($data['country']),
            ]);

            if (array_key_exists('is_active', $data)) {
                $clinic->is_active = (bool) $data['is_active'];
            }

            $clinic->save();

            $freshClinic = $clinic->fresh();
            $newValues = $this->auditLogService->clinicSnapshot($freshClinic);
            $this->auditLogService->logClinicUpdated($freshClinic, $oldValues, $newValues);

            return $freshClinic;
        });
    }

    public function deactivate(Clinic $clinic): Clinic
    {
        return $this->update($clinic, [
            'name' => $clinic->name,
            'code' => $clinic->code,
            'currency' => $clinic->currency,
            'timezone' => $clinic->timezone,
            'country' => $clinic->country,
            'is_active' => false,
        ]);
    }

    public function activate(Clinic $clinic): Clinic
    {
        return $this->update($clinic, [
            'name' => $clinic->name,
            'code' => $clinic->code,
            'currency' => $clinic->currency,
            'timezone' => $clinic->timezone,
            'country' => $clinic->country,
            'is_active' => true,
        ]);
    }
}
