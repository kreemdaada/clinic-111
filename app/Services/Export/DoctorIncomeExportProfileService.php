<?php

namespace App\Services\Export;

use App\Models\Doctor;
use App\Models\DoctorIncomeExportProfile;
use App\Services\Configuration\CurrentClinicResolver;
use App\Support\DoctorLabelNormalizer;
use Illuminate\Support\Collection;

/**
 * Loads doctor-specific Server Income Excel layout from the database.
 *
 * Replaces hardcoded profile arrays — add/change doctors via DB + seeder/admin, not code deploy.
 * JOB calculation is unchanged: still {@see \App\Services\Accounting\LabJobCalculationService}
 * with prices from {@see \App\Services\Accounting\LabPriceResolver}.
 */
class DoctorIncomeExportProfileService
{
    /** @var Collection<string, DoctorIncomeExportProfile>|null */
    private ?Collection $profilesByDoctorCode = null;

    public function __construct(
        private readonly CurrentClinicResolver $currentClinicResolver,
    ) {}

    /**
     * Resolve export profile array for a doctor (same shape the Excel export expects).
     *
     * @return array<string, mixed>|null Profile or null when no row exists in DB.
     */
    public function resolveForDoctor(Doctor $doctor): ?array
    {
        $code = DoctorLabelNormalizer::extractCodeGuess($doctor->code);
        $profile = $this->profilesByDoctorCode()->get($code);

        if ($profile === null) {
            return null;
        }

        return $this->toProfileArray($profile);
    }

    /**
     * Income Excel column letter for doctor + treatment, from DB profile.
     */
    public function columnFor(?string $doctorCode, string $treatmentCode): ?string
    {
        if ($doctorCode === null || $doctorCode === '') {
            return null;
        }

        $columns = $this->columnsForDoctor($doctorCode);

        return $columns[strtoupper(trim($treatmentCode))] ?? null;
    }

    /**
     * All treatment → column mappings for one doctor.
     *
     * @return array<string, string>
     */
    public function columnsForDoctor(?string $doctorCode): array
    {
        if ($doctorCode === null || $doctorCode === '') {
            return [];
        }

        $code = DoctorLabelNormalizer::extractCodeGuess($doctorCode);
        $profile = $this->profilesByDoctorCode()->get($code);

        if ($profile === null) {
            return [];
        }

        return $profile->treatment_columns ?? [];
    }

    /**
     * True when the doctor profile has no treatment columns (payments-only sheet).
     */
    public function isPaymentsOnlyDoctor(?string $doctorCode): bool
    {
        return $this->columnsForDoctor($doctorCode) === [];
    }

    /**
     * Clear in-memory cache (e.g. after seeding in tests).
     */
    public function forgetCachedProfiles(): void
    {
        $this->profilesByDoctorCode = null;
    }

    /**
     * @return Collection<string, DoctorIncomeExportProfile> Keyed by normalized doctor code.
     */
    private function profilesByDoctorCode(): Collection
    {
        if ($this->profilesByDoctorCode !== null) {
            return $this->profilesByDoctorCode;
        }

        $this->profilesByDoctorCode = DoctorIncomeExportProfile::query()
            ->whereHas('doctor', fn ($query) => $query->where('clinic_id', $this->currentClinicResolver->resolveId()))
            ->with('doctor')
            ->get()
            ->keyBy(fn (DoctorIncomeExportProfile $profile): string => DoctorLabelNormalizer::extractCodeGuess($profile->doctor->code));

        return $this->profilesByDoctorCode;
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
