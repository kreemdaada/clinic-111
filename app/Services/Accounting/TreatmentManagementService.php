<?php

namespace App\Services\Accounting;

use App\Models\Treatment;
use App\Services\Audit\AuditLogService;
use App\Services\Configuration\Concerns\ScopesConfigurationQueries;
use App\Services\Configuration\CurrentClinicResolver;
use App\Services\DailyReport\DoctorManagementService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Admin management of treatment master data — never physically delete financial configuration.
 */
class TreatmentManagementService
{
    use ScopesConfigurationQueries;

    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly CurrentClinicResolver $currentClinicResolver,
        private readonly DoctorManagementService $doctorManagementService,
    ) {}

    public function listQuery(?string $search = null, string $status = 'all'): Builder
    {
        $query = $this->forCurrentClinic(Treatment::class);

        if ($search !== null && trim($search) !== '') {
            $term = '%'.trim($search).'%';
            $query->where(function (Builder $builder) use ($term) {
                $builder
                    ->where('name', 'like', $term)
                    ->orWhere('code', 'like', $term)
                    ->orWhere('description', 'like', $term);
            });
        }

        if ($status === 'active') {
            $query->where('is_active', true);
        }

        if ($status === 'inactive') {
            $query->where('is_active', false);
        }

        return $query;
    }

    /**
     * @return Collection<int, Treatment>
     */
    public function listActive(): Collection
    {
        return $this->forCurrentClinic(Treatment::class)
            ->where('is_active', true)
            ->orderBy('code')
            ->get();
    }

    /**
     * @return Collection<int, Treatment>
     */
    public function listActiveWithLabCost(): Collection
    {
        return $this->forCurrentClinic(Treatment::class)
            ->where('has_lab_cost', true)
            ->orderBy('code')
            ->get();
    }

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
                'clinic_id' => $this->currentClinicId(),
                'code' => strtoupper(trim($data['code'])),
                'name' => trim($data['name']),
                'description' => isset($data['description']) ? trim((string) $data['description']) : null,
            ]);
            $treatment->has_lab_cost = (bool) ($data['has_lab_cost'] ?? false);
            $treatment->is_active = true;
            $treatment->save();

            $this->auditLogService->logTreatmentCreated($treatment->fresh());
            $this->doctorManagementService->syncLabBillingForTreatment($treatment->fresh());

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
        $this->assertSameClinic($treatment);

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

            if ($freshTreatment->has_lab_cost) {
                $this->doctorManagementService->syncLabBillingForTreatment($freshTreatment);
            }

            return $freshTreatment;
        });
    }

    public function deactivate(Treatment $treatment): Treatment
    {
        $this->assertSameClinic($treatment);

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
        $this->assertSameClinic($treatment);

        return $this->update($treatment, [
            'code' => $treatment->code,
            'name' => $treatment->name,
            'description' => $treatment->description,
            'has_lab_cost' => $treatment->has_lab_cost,
            'is_active' => true,
        ]);
    }
}
