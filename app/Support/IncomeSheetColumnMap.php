<?php

namespace App\Support;

/**
 * Maps doctor + treatment code → Income Excel column letter (H–P).
 * Must stay in sync with DoctorsIncomeExcelExportService profiles.
 */
final class IncomeSheetColumnMap
{
    /** @var array<string, array<string, string>> */
    private const MAP = [
        'JACK' => [
            'MC' => 'H', 'ZIR' => 'I', 'IMPL-CR' => 'J', 'IMPL-ZIR' => 'K', 'VENEER' => 'L',
            'IMPL' => 'N', 'POST' => 'P', 'ABT' => 'Q', 'REMOV' => 'R',
        ],
        'RIYAD' => [
            'MC' => 'H', 'ZIR' => 'I', 'IMPL-CR' => 'J', 'IMPL-ZIR' => 'K', 'VENEER' => 'L',
            'IMPL' => 'M', 'POST' => 'N', 'ABT' => 'O', 'REMOV' => 'P',
        ],
        'PURIYA' => [],
    ];

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
     * @return array<string, string>
     */
    public static function columnsForDoctor(?string $doctorCode): array
    {
        if ($doctorCode === null) {
            return [];
        }

        $normalizedDoctor = DoctorLabelNormalizer::extractCodeGuess($doctorCode);

        return self::MAP[$normalizedDoctor] ?? [];
    }

    public static function isPaymentsOnlyDoctor(?string $doctorCode): bool
    {
        return DoctorLabelNormalizer::extractCodeGuess((string) $doctorCode) === 'PURIYA';
    }
}
