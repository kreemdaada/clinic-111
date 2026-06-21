<?php

namespace App\Support;

/**
 * Maps doctor + treatment code → Income Excel column letter (H–P).
 *
 * Must stay in sync with {@see \App\Services\Export\DoctorsIncomeExcelExportService} profiles.
 * Puriya (PURIYA) is payments-only — no treatment columns.
 */
final class IncomeSheetColumnMap
{
    /** @var array<string, array<string, string>> Doctor code → treatment code → Excel column. */
    private const MAP = [
        'JACK' => [
            'MC' => 'H',
            'ZIR' => 'I',
            'IMPL-CR' => 'J',
            'IMPL-ZIR' => 'K',
            'VENEER' => 'L',
            'IMPL' => 'N',
            'POST' => 'P',
            'ABT' => 'Q',
            'REMOV' => 'R',
        ],
        'RIYAD' => [
            'MC' => 'H',
            'ZIR' => 'I',
            'IMPL-CR' => 'J',
            'IMPL-ZIR' => 'K',
            'VENEER' => 'L',
            'IMPL' => 'M',
            'POST' => 'N',
            'ABT' => 'O',
            'REMOV' => 'P',
        ],
        'PURIYA' => [
            'MC' => 'H',
            'ZIR' => 'I',
            'IMPL-CR' => 'J',
            'IMPL-ZIR' => 'K',
            'VENEER' => 'L',
            'IMPL' => 'M',
            'POST' => 'N',
            'ABT' => 'O',
            'REMOV' => 'P'
        ],
    ];

    /**
     * Resolve the Income Excel column for a doctor + treatment pair.
     *
     * @param  string|null  $doctorCode  Canonical or alias doctor code.
     * @param  string  $treatmentCode  Treatment code (e.g. MC, ZIR).
     * @return string|null Column letter (H–P) or null when not exported for this doctor.
     */
    public static function columnFor(?string $doctorCode, string $treatmentCode): ?string
    {
        if ($doctorCode === null || $doctorCode === '') {
            return null;
        }

        $normalizedDoctor = DoctorLabelNormalizer::extractCodeGuess($doctorCode);

        if ($normalizedDoctor === 'PURIYA') {
            return null;
        }

        $columns = self::MAP[$normalizedDoctor] ?? null;

        if ($columns === null) {
            return null;
        }

        return $columns[strtoupper(trim($treatmentCode))] ?? null;
    }

    /**
     * Return all treatment → column mappings for one doctor.
     *
     * @param  string|null  $doctorCode  Canonical or alias doctor code.
     * @return array<string, string> Treatment code → column letter.
     */
    public static function columnsForDoctor(?string $doctorCode): array
    {
        if ($doctorCode === null) {
            return [];
        }

        $normalizedDoctor = DoctorLabelNormalizer::extractCodeGuess($doctorCode);

        return self::MAP[$normalizedDoctor] ?? [];
    }

    /**
     * Whether this doctor's Income sheet has only payment columns (no H–P treatment counts).
     *
     * @param  string|null  $doctorCode  Canonical or alias doctor code.
     */
    public static function isPaymentsOnlyDoctor(?string $doctorCode): bool
    {
        return DoctorLabelNormalizer::extractCodeGuess((string) $doctorCode) === 'PURIYA';
    }
}
