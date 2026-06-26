<?php

namespace App\Services\Accounting;

use App\Models\Lab;
use App\Services\Audit\AuditLogService;
use App\Services\Configuration\Concerns\ScopesConfigurationQueries;
use App\Services\Configuration\CurrentClinicResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Admin management of laboratory master data — never physically delete financial configuration.
 */
class LabManagementService
{
    use ScopesConfigurationQueries;

    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly CurrentClinicResolver $currentClinicResolver,
    ) {}

    public function listQuery(?string $search = null, string $status = 'all'): Builder
    {
        $query = $this->forCurrentClinic(Lab::class);

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
     * @return Collection<int, Lab>
     */
    public function listActive(): Collection
    {
        return $this->forCurrentClinic(Lab::class)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    /**
     * @param  array{name: string, code: string}  $data
     */
    public function create(array $data): Lab
    {
        return DB::transaction(function () use ($data) {
            $lab = Lab::query()->create([
                'clinic_id' => $this->currentClinicId(),
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
        $this->assertSameClinic($lab);

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
        $this->assertSameClinic($lab);

        return $this->update($lab, [
            'name' => $lab->name,
            'code' => $lab->code,
            'is_active' => false,
        ]);
    }

    public function activate(Lab $lab): Lab
    {
        $this->assertSameClinic($lab);

        return $this->update($lab, [
            'name' => $lab->name,
            'code' => $lab->code,
            'is_active' => true,
        ]);
    }
}
