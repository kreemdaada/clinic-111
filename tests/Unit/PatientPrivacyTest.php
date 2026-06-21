<?php

namespace Tests\Unit;

use App\Support\ImportRowPrivacySanitizer;
use App\Support\PatientReferenceHasher;
use Tests\TestCase;

class PatientPrivacyTest extends TestCase
{
    public function test_patient_reference_hash_is_deterministic_and_non_reversible(): void
    {
        $hasher = app(PatientReferenceHasher::class);

        $hash = $hasher->hash('John Doe', 'MRN123', 'F-456');
        $sameHash = $hasher->hash('John Doe', 'MRN123', 'F-456');

        $this->assertSame($hash, $sameHash);
        $this->assertSame(64, strlen((string) $hash));
        $this->assertNotSame('John Doe', $hash);
    }

    public function test_patient_reference_hash_is_null_when_all_identifiers_empty(): void
    {
        $hasher = app(PatientReferenceHasher::class);

        $this->assertNull($hasher->hash(null, null, null));
        $this->assertNull($hasher->hash('', '', ''));
    }

    public function test_import_row_sanitizer_removes_patient_identifiers(): void
    {
        $sanitizer = app(ImportRowPrivacySanitizer::class);

        $sanitized = $sanitizer->sanitize([
            'patient_name' => 'Jane Doe',
            'mrn' => '12345',
            'file_number' => 'F-99',
            'doctor' => 'Dr Riyad',
            'treatment_text' => 'ZIR x 2',
            'raw_cells' => [
                'A' => 'Jane Doe',
                'B' => '12345',
                'C' => 'F-99',
                'G' => 'ZIR x 2',
            ],
            '_column_map' => [
                'patient_name' => 'A',
                'mrn' => 'B',
                'file_number' => 'C',
            ],
        ]);

        $this->assertArrayNotHasKey('patient_name', $sanitized);
        $this->assertArrayNotHasKey('mrn', $sanitized);
        $this->assertArrayNotHasKey('file_number', $sanitized);
        $this->assertSame('Dr Riyad', $sanitized['doctor']);
        $this->assertSame('[REDACTED]', $sanitized['raw_cells']['A']);
        $this->assertSame('[REDACTED]', $sanitized['raw_cells']['B']);
        $this->assertSame('[REDACTED]', $sanitized['raw_cells']['C']);
        $this->assertSame('ZIR x 2', $sanitized['raw_cells']['G']);
    }
}
