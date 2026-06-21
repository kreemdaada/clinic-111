<?php

namespace App\Support;

/**
 * Derives a non-reversible patient reference hash from identifiers read during import.
 *
 * Identifiers are never persisted; only the HMAC-SHA256 digest is stored.
 */
class PatientReferenceHasher
{
    /**
     * @param  string|null  $patientName  Patient name from Excel (memory only).
     * @param  string|null  $mrn  Medical record number (memory only).
     * @param  string|null  $fileNumber  Clinic file number (memory only).
     * @return string|null 64-char hex digest, or null when all identifiers are empty.
     */
    public function hash(?string $patientName, ?string $mrn, ?string $fileNumber): ?string
    {
        $normalizedName = strtolower(trim((string) $patientName));
        $normalizedMrn = trim((string) $mrn);
        $normalizedFile = trim((string) $fileNumber);

        if ($normalizedName === '' && $normalizedMrn === '' && $normalizedFile === '') {
            return null;
        }

        $payload = implode('|', [$normalizedName, $normalizedMrn, $normalizedFile]);
        $key = config('accounting.patient_reference_hmac_key');

        if (! is_string($key) || $key === '') {
            throw new \RuntimeException(
                'ACCOUNTING_PATIENT_REFERENCE_HMAC_KEY must be set to a dedicated secret (not APP_KEY).',
            );
        }

        return hash_hmac('sha256', $payload, $key);
    }
}
