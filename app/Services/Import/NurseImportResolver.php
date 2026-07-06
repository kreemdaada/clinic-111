<?php

namespace App\Services\Import;

use App\DTOs\NurseImportResolution;
use App\Models\Nurse;
use App\Services\Configuration\Concerns\ScopesConfigurationQueries;
use App\Services\Configuration\CurrentClinicResolver;
use Illuminate\Support\Collection;

/**
 * Tenant-safe nurse alias resolution for Excel import rows.
 */
class NurseImportResolver
{
    use ScopesConfigurationQueries;

    public function __construct(
        private readonly CurrentClinicResolver $currentClinicResolver,
    ) {}

    public function resolveOptionalAlias(?string $alias): NurseImportResolution
    {
        $alias = trim((string) $alias);

        if ($alias === '') {
            return NurseImportResolution::none();
        }

        $matches = $this->findActiveMatches($alias);

        if ($matches->isEmpty()) {
            $inactive = $this->findInactiveExactMatch($alias);

            if ($inactive !== null) {
                return NurseImportResolution::inactive($inactive);
            }

            return NurseImportResolution::unresolved($alias);
        }

        if ($matches->count() > 1) {
            return NurseImportResolution::ambiguous($alias);
        }

        return NurseImportResolution::matched($matches->first());
    }

    /**
     * @return Collection<int, Nurse>
     */
    private function findActiveMatches(string $alias): Collection
    {
        $needle = strtoupper($alias);

        return $this->forCurrentClinic(Nurse::class)
            ->where('is_active', true)
            ->where(function ($query) use ($needle) {
                $query
                    ->whereRaw('UPPER(code) = ?', [$needle])
                    ->orWhereRaw('UPPER(name) = ?', [$needle])
                    ->orWhereRaw('UPPER(name) LIKE ?', ['%'.$needle.'%']);
            })
            ->get()
            ->filter(function (Nurse $nurse) use ($needle, $alias) {
                $code = strtoupper((string) $nurse->code);
                $name = strtoupper((string) $nurse->name);

                if ($code === $needle || $name === $needle) {
                    return true;
                }

                return str_contains($name, $needle) && strlen($alias) >= 3;
            })
            ->values();
    }

    private function findInactiveExactMatch(string $alias): ?Nurse
    {
        $needle = strtoupper($alias);

        return $this->forCurrentClinic(Nurse::class)
            ->where('is_active', false)
            ->where(function ($query) use ($needle) {
                $query
                    ->whereRaw('UPPER(code) = ?', [$needle])
                    ->orWhereRaw('UPPER(name) = ?', [$needle]);
            })
            ->first();
    }
}
