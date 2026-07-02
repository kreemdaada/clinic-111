<?php

namespace App\Services\Accounting;

use App\Models\Nurse;
use App\Models\Treatment;
use App\Services\Configuration\Concerns\ScopesConfigurationQueries;
use App\Services\Configuration\CurrentClinicResolver;
use App\Services\Configuration\TenantResourceGuard;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Validates nurse commission requirements for manual daily report work rows.
 */
class NurseCommissionWorkRowValidator
{
    use ScopesConfigurationQueries;

    public function __construct(
        private readonly NurseCommissionRateResolver $nurseCommissionRateResolver,
        private readonly TenantResourceGuard $tenantResourceGuard,
        private readonly CurrentClinicResolver $currentClinicResolver,
    ) {}

    /**
     * @param  array<int, array{code: string, quantity: int, nurse_id?: int|null}>  $treatmentLines
     * @return array<string, string>
     */
    public function validate(array $treatmentLines): array
    {
        $errors = [];

        foreach ($treatmentLines as $index => $line) {
            $code = strtoupper(trim((string) ($line['code'] ?? '')));
            $treatment = $this->forCurrentClinic(Treatment::class)
                ->where('code', $code)
                ->where('is_active', true)
                ->first();

            if ($treatment === null || ! $treatment->requires_nurse_commission) {
                continue;
            }

            $prefix = "treatment_lines.{$index}";

            if ($treatment->treatment_price === null || $treatment->treatment_price_currency === null) {
                $errors["{$prefix}.code"] = "Treatment price is required for {$treatment->name} before nurse commission can be calculated.";

                continue;
            }

            $nurseId = $line['nurse_id'] ?? null;

            if ($nurseId === null || $nurseId === '') {
                $errors["{$prefix}.nurse_id"] = "A nurse must be selected for {$treatment->name}.";

                continue;
            }

            try {
                $nurse = $this->tenantResourceGuard->findAccessibleOrAbort(Nurse::class, (int) $nurseId);
            } catch (ModelNotFoundException|HttpException) {
                $errors["{$prefix}.nurse_id"] = 'The selected nurse is not available for this clinic.';

                continue;
            }

            if (! $nurse->is_active) {
                $errors["{$prefix}.nurse_id"] = 'The selected nurse must be active.';

                continue;
            }

            if ($this->nurseCommissionRateResolver->resolveActive($nurse, $treatment) === null) {
                $errors["{$prefix}.nurse_id"] = "No active commission rate is configured for {$nurse->name} and {$treatment->name}.";
            }
        }

        return $errors;
    }
}
