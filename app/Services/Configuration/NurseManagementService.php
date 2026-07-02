<?php

namespace App\Services\Configuration;

use App\Models\Nurse;
use App\Services\Audit\AuditLogService;
use App\Services\Configuration\Concerns\ScopesConfigurationQueries;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Admin management of nurse master data — never physically delete configuration rows.
 */
class NurseManagementService
{
    use ScopesConfigurationQueries;

    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly CurrentClinicResolver $currentClinicResolver,
    ) {}

    public function listQuery(?string $search = null, string $status = 'all'): Builder
    {
        $query = $this->forCurrentClinic(Nurse::class);

        if ($search !== null && trim($search) !== '') {
            $term = '%'.trim($search).'%';
            $query->where(function (Builder $builder) use ($term) {
                $builder
                    ->where('name', 'like', $term)
                    ->orWhere('code', 'like', $term);
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
     * @return Collection<int, Nurse>
     */
    public function listActive(): Collection
    {
        return $this->forCurrentClinic(Nurse::class)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    /**
     * @param  array{name: string, code: string}  $data
     */
    public function create(array $data): Nurse
    {
        return DB::transaction(function () use ($data) {
            $nurse = Nurse::query()->create([
                'clinic_id' => $this->currentClinicId(),
                'name' => trim($data['name']),
                'code' => strtoupper(trim($data['code'])),
                'is_active' => true,
            ]);

            $this->auditLogService->logNurseCreated($nurse);

            return $nurse->fresh();
        });
    }

    /**
     * @param  array{name: string, code: string, is_active?: bool}  $data
     */
    public function update(Nurse $nurse, array $data): Nurse
    {
        $this->assertSameClinic($nurse);

        return DB::transaction(function () use ($nurse, $data) {
            $oldValues = $this->auditLogService->nurseSnapshot($nurse);

            $nurse->fill([
                'name' => trim($data['name']),
                'code' => strtoupper(trim($data['code'])),
            ]);

            if (array_key_exists('is_active', $data)) {
                $nurse->is_active = (bool) $data['is_active'];
            }

            $nurse->save();

            $freshNurse = $nurse->fresh();
            $newValues = $this->auditLogService->nurseSnapshot($freshNurse);
            $this->auditLogService->logNurseUpdated($freshNurse, $oldValues, $newValues);

            return $freshNurse;
        });
    }

    public function deactivate(Nurse $nurse): Nurse
    {
        $this->assertSameClinic($nurse);

        return $this->update($nurse, [
            'name' => $nurse->name,
            'code' => $nurse->code,
            'is_active' => false,
        ]);
    }

    public function activate(Nurse $nurse): Nurse
    {
        $this->assertSameClinic($nurse);

        return $this->update($nurse, [
            'name' => $nurse->name,
            'code' => $nurse->code,
            'is_active' => true,
        ]);
    }
}
