<?php

namespace App\Services\Import;

use App\DTOs\ImportParseWarningDto;
use App\DTOs\NurseImportResolution;
use App\Models\Nurse;
use App\Services\Accounting\WorkItemNurseAssignmentService;
use App\Support\OpgClinicDoctor;
use App\Support\OpgTreatmentLabelNormalizer;

/**
 * Shared OPG row assembly for Excel import (treatment text + nurse assignments).
 *
 * Manual daily-report entry uses the same treatment codes and nurse assignment storage
 * via {@see WorkItemNurseAssignmentService}.
 */
class OpgImportRowAssembler
{
    public function __construct(
        private readonly NurseImportResolver $nurseImportResolver,
    ) {}

    /**
     * @return array{
     *     treatment_text: string,
     *     nurse_assignments: array<string, int>,
     *     warnings: array<int, ImportParseWarningDto>,
     * }
     */
    public function assembleFromParsedOpgRow(
        string $treatmentCode,
        int $quantity,
        ?string $columnNurseAlias,
        ?string $treatmentNurseAlias,
        ?string $unmappedNurseCandidate,
        int $excelRow,
        string $treatmentLabelForWarnings = '',
    ): array {
        $explicitAlias = $this->firstNonEmptyAlias($columnNurseAlias, $treatmentNurseAlias);
        $resolvedNurse = null;
        $warnings = [];

        if ($explicitAlias !== null) {
            $resolution = $this->nurseImportResolver->resolveOptionalAlias($explicitAlias);
            $warnings = $this->warningsFromResolution(
                $resolution,
                $excelRow,
                $treatmentLabelForWarnings,
                $explicitAlias,
            );
            $resolvedNurse = $resolution->nurse;
        } elseif ($unmappedNurseCandidate !== null) {
            $resolvedNurse = $this->resolveUnmappedCandidate($unmappedNurseCandidate);
        }

        $assignments = [];

        if ($resolvedNurse !== null) {
            $assignments[$treatmentCode] = (int) $resolvedNurse->id;
        }

        return [
            'treatment_text' => OpgTreatmentLabelNormalizer::treatmentText($treatmentCode, $quantity),
            'nurse_assignments' => $assignments,
            'warnings' => $warnings,
        ];
    }

    /**
     * @return array{
     *     treatment_text: string,
     *     nurse_assignments: array<string, int>,
     *     warnings: array<int, ImportParseWarningDto>,
     * }
     */
    public function assembleFromTreatmentLabel(
        string $treatmentLabel,
        ?string $columnNurseAlias,
        int $excelRow,
    ): array {
        $parsed = OpgTreatmentLabelNormalizer::parse($treatmentLabel);

        if ($parsed === null) {
            return [
                'treatment_text' => '',
                'nurse_assignments' => [],
                'warnings' => [
                    new ImportParseWarningDto(
                        excelRow: $excelRow,
                        doctor: OpgClinicDoctor::IMPORT_LABEL,
                        treatmentText: $treatmentLabel,
                        warningCode: 'opg_label_unrecognized',
                        message: 'OPG treatment label could not be normalized.',
                    ),
                ],
            ];
        }

        return $this->assembleFromParsedOpgRow(
            treatmentCode: $parsed['code'],
            quantity: $parsed['quantity'],
            columnNurseAlias: $columnNurseAlias,
            treatmentNurseAlias: $parsed['nurse_alias'] ?? null,
            unmappedNurseCandidate: null,
            excelRow: $excelRow,
            treatmentLabelForWarnings: $treatmentLabel,
        );
    }

    private function resolveUnmappedCandidate(string $candidate): ?Nurse
    {
        $resolution = $this->nurseImportResolver->resolveOptionalAlias($candidate);

        if ($resolution->nurse === null || $resolution->warningCode !== null) {
            return null;
        }

        return $resolution->nurse;
    }

    private function firstNonEmptyAlias(?string ...$aliases): ?string
    {
        foreach ($aliases as $alias) {
            $alias = trim((string) $alias);

            if ($alias !== '') {
                return $alias;
            }
        }

        return null;
    }

    /**
     * @return array<int, ImportParseWarningDto>
     */
    private function warningsFromResolution(
        NurseImportResolution $resolution,
        int $excelRow,
        string $treatmentLabel,
        ?string $nurseAlias,
    ): array {
        if ($resolution->warningCode === null) {
            return [];
        }

        return [
            new ImportParseWarningDto(
                excelRow: $excelRow,
                doctor: OpgClinicDoctor::IMPORT_LABEL,
                treatmentText: $treatmentLabel,
                warningCode: $resolution->warningCode,
                message: (string) $resolution->message,
            ),
        ];
    }
}
