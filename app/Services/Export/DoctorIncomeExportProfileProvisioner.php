<?php

namespace App\Services\Export;

use App\Models\Doctor;
use App\Models\DoctorIncomeExportProfile;
use App\Support\IncomeExportStandardLayout;

/**
 * Creates default Server Income export profiles in the database (ADR-623).
 */
class DoctorIncomeExportProfileProvisioner
{
    /**
     * Ensure the doctor has a persisted export profile; return the profile array for export.
     *
     * @return array<string, mixed>
     */
    public function ensureForDoctor(Doctor $doctor): array
    {
        $existing = DoctorIncomeExportProfile::query()
            ->where('doctor_id', $doctor->id)
            ->first();

        if ($existing !== null) {
            return $this->toProfileArray($existing);
        }

        $sheetName = $this->defaultSheetName($doctor);
        $config = IncomeExportStandardLayout::defaultProfileConfig($sheetName);

        $profile = DoctorIncomeExportProfile::query()->create([
            'doctor_id' => $doctor->id,
            ...$config,
        ]);

        return $this->toProfileArray($profile);
    }

    private function defaultSheetName(Doctor $doctor): string
    {
        $label = trim($doctor->name);

        if ($label === '') {
            $label = trim($doctor->code);
        }

        return 'Dr. ' . $label;
    }

    /**
     * @return array<string, mixed>
     */
    private function toProfileArray(DoctorIncomeExportProfile $profile): array
    {
        return [
            'sheet_name' => $profile->sheet_name,
            'layout' => $profile->layout,
            'first_day_row' => $profile->first_day_row,
            'write_payment_headers' => $profile->write_payment_headers,
            'summary_shows_net_total' => $profile->summary_shows_net_total,
            'payment_columns' => $profile->payment_columns ?? [],
            'treatment_columns' => $profile->treatment_columns ?? [],
        ];
    }
}
