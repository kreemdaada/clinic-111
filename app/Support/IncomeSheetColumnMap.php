<?php

namespace App\Support;

use App\Services\Export\DoctorIncomeExportProfileService;

/**
 * Thin facade for treatment → Excel column lookup in diagnostics.
 *
 * Data lives in `doctor_income_export_profiles` — see {@see DoctorIncomeExportProfileService}.
 */
final class IncomeSheetColumnMap
{
    /**
     * Resolve the Income Excel column for a doctor + treatment pair.
     */
    public static function columnFor(?string $doctorCode, string $treatmentCode): ?string
    {
        return app(DoctorIncomeExportProfileService::class)->columnFor($doctorCode, $treatmentCode);
    }

    /**
     * Return all treatment → column mappings for one doctor.
     *
     * @return array<string, string>
     */
    public static function columnsForDoctor(?string $doctorCode): array
    {
        return app(DoctorIncomeExportProfileService::class)->columnsForDoctor($doctorCode);
    }

    /**
     * Whether this doctor's sheet has no treatment columns (payments-only layout).
     */
    public static function isPaymentsOnlyDoctor(?string $doctorCode): bool
    {
        return app(DoctorIncomeExportProfileService::class)->isPaymentsOnlyDoctor($doctorCode);
    }
}
