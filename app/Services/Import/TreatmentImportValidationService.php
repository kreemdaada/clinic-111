<?php

namespace App\Services\Import;

use App\DTOs\ImportParseWarningDto;
use App\DTOs\ParsedTreatmentItemDto;
use App\DTOs\TreatmentImportResultDto;
use App\Models\DailyWorkRow;
use App\Models\Treatment;
use App\Models\WorkItem;
use App\Services\Accounting\LabBillingResolver;
use App\Services\Accounting\TreatmentParserService;
use Illuminate\Support\Collection;

/**
 * Validates treatment_text during import, emits warnings, and persists all valid work items.
 */
class TreatmentImportValidationService
{
    public function __construct(
        private readonly TreatmentParserService $treatmentParserService,
        private readonly LabBillingResolver $labBillingResolver,
    ) {}

    /**
     * Validate treatment text, persist valid work items, return warnings.
     */
    public function validateAndPersist(DailyWorkRow $dailyWorkRow): TreatmentImportResultDto
    {
        $dailyWorkRow->loadMissing('doctor');
        $dailyWorkRow->workItems()->delete();

        $treatmentText = trim((string) $dailyWorkRow->treatment_text);

        if ($treatmentText === '') {
            return new TreatmentImportResultDto(0, []);
        }

        $doctorLabel = $dailyWorkRow->doctor?->name ?? $dailyWorkRow->doctor?->code ?? 'Unknown';
        $excelRow = (int) ($dailyWorkRow->excel_row_number ?? 0);
        $warnings = [];
        $validItems = [];

        foreach ($this->splitTreatmentParts($treatmentText) as $part) {
            $partWarnings = $this->validatePart(
                $part,
                $doctorLabel,
                $excelRow,
                $treatmentText,
            );

            if ($partWarnings !== []) {
                $warnings = array_merge($warnings, $partWarnings);

                continue;
            }

            $parsedItems = $this->treatmentParserService->parse($part);

            foreach ($parsedItems as $parsedItem) {
                $treatment = $this->getKnownTreatmentCodes()->get($parsedItem->treatmentCode);

                if ($treatment === null) {
                    $warnings[] = $this->unknownCodeWarning(
                        $doctorLabel,
                        $excelRow,
                        $treatmentText,
                        $parsedItem->treatmentCode,
                    );

                    continue;
                }

                if ($parsedItem->quantity <= 0) {
                    $warnings[] = $this->missingQuantityWarning(
                        $doctorLabel,
                        $excelRow,
                        $treatmentText,
                        $parsedItem->treatmentCode,
                    );

                    continue;
                }

                $validItems[] = $parsedItem;
            }
        }

        $persistedCount = 0;

        foreach ($this->mergeParsedItemsByCode($validItems) as $parsedItem) {
            $treatment = $this->getKnownTreatmentCodes()->get($parsedItem->treatmentCode);

            if ($treatment === null) {
                continue;
            }

            WorkItem::query()->create([
                'daily_work_row_id' => $dailyWorkRow->id,
                'treatment_id' => $treatment->id,
                'quantity' => $parsedItem->quantity,
                'confidence' => $parsedItem->confidence,
                'warning_message' => $parsedItem->warningMessage,
            ]);

            $persistedCount++;
        }

        return new TreatmentImportResultDto($persistedCount, $warnings);
    }

    /**
     * Collect lab-price warnings for work items that have lab cost but no lab job.
     *
     * @return array<int, ImportParseWarningDto>
     */
    public function collectLabPriceWarnings(DailyWorkRow $dailyWorkRow): array
    {
        $dailyWorkRow->loadMissing(['doctor', 'workItems.treatment', 'workItems.labJob']);

        $doctorLabel = $dailyWorkRow->doctor?->name ?? $dailyWorkRow->doctor?->code ?? 'Unknown';
        $excelRow = (int) ($dailyWorkRow->excel_row_number ?? 0);
        $warnings = [];

        foreach ($dailyWorkRow->workItems as $workItem) {
            $doctor = $dailyWorkRow->doctor;

            if ($doctor === null || ! $this->labBillingResolver->shouldBillLabJob($doctor, $workItem->treatment)) {
                continue;
            }

            if ($workItem->labJob !== null) {
                continue;
            }

            $code = $workItem->treatment->code;

            $warnings[] = new ImportParseWarningDto(
                excelRow: $excelRow,
                doctor: $doctorLabel,
                treatmentText: $dailyWorkRow->treatment_text,
                warningCode: 'lab_price_not_found',
                message: "Treatment {$code} has lab cost but no lab price was found for {$doctorLabel}.",
            );
        }

        return $warnings;
    }

    /**
     * @return array<int, string>
     */
    private function splitTreatmentParts(string $treatmentText): array
    {
        $segments = preg_split('/\s\|\s/', $treatmentText) ?: [$treatmentText];
        $parts = [];

        foreach ($segments as $segment) {
            $segment = trim($segment);

            if ($segment === '') {
                continue;
            }

            $subParts = preg_split('/\s*\+\s*/', $segment) ?: [$segment];

            foreach ($subParts as $subPart) {
                $subPart = trim($subPart);

                if ($subPart !== '') {
                    $parts[] = $subPart;
                }
            }
        }

        return $parts;
    }

    /**
     * @return array<int, ImportParseWarningDto>
     */
    private function validatePart(
        string $part,
        string $doctorLabel,
        int $excelRow,
        string $fullTreatmentText,
    ): array {
        $parsedItems = $this->treatmentParserService->parse($part);

        if ($parsedItems !== []) {
            if (! $this->hasRecognizedQuantityNotation($part)) {
                $code = $parsedItems[0]->treatmentCode;

                return [
                    $this->missingQuantityWarning($doctorLabel, $excelRow, $fullTreatmentText, $code),
                ];
            }

            return [];
        }

        if (preg_match('/^([A-Za-z][A-Za-z0-9\-]*)\s*$/', $part, $matches) === 1) {
            $code = strtoupper($matches[1]);

            return [
                $this->missingQuantityWarning($doctorLabel, $excelRow, $fullTreatmentText, $code),
            ];
        }

        if (preg_match('/^([A-Za-z][A-Za-z0-9\-]*)\s+[xX×]\s*$/', $part, $matches) === 1) {
            $code = strtoupper($matches[1]);

            return [
                $this->missingQuantityWarning($doctorLabel, $excelRow, $fullTreatmentText, $code),
            ];
        }

        if (preg_match('/^([A-Za-z][A-Za-z0-9\-]*)\s+[xX×]\s*(\d+)/', $part, $matches) === 1) {
            $code = strtoupper($matches[1]);
            $knownCodes = $this->getKnownTreatmentCodes();

            if (! $knownCodes->has($code)) {
                return [
                    $this->unknownCodeWarning($doctorLabel, $excelRow, $fullTreatmentText, $code),
                ];
            }

            return [];
        }

        $suggestion = $this->suggestStandardFormat($part);

        return [
            new ImportParseWarningDto(
                excelRow: $excelRow,
                doctor: $doctorLabel,
                treatmentText: $fullTreatmentText,
                warningCode: 'invalid_format',
                message: $suggestion !== null
                    ? "Invalid format. Use {$suggestion}"
                    : 'Invalid format. Use CODE x QUANTITY (e.g. ZIR x 2).',
            ),
        ];
    }

    private function suggestStandardFormat(string $part): ?string
    {
        if (preg_match('/^zircon\s+(\d+)/i', $part, $matches) === 1) {
            return 'ZIR x '.$matches[1];
        }

        if (preg_match('/^([A-Za-z][A-Za-z0-9\-]*)\s+(\d+)/', $part, $matches) === 1) {
            return strtoupper($matches[1]).' x '.$matches[2];
        }

        if (preg_match('/^zircon/i', $part) === 1) {
            return 'ZIR x 1';
        }

        if (preg_match('/^([A-Za-z]+)/i', $part, $matches) === 1) {
            $guess = strtoupper($matches[1]);

            if (str_starts_with($guess, 'ZIR')) {
                return 'ZIR x 1';
            }
        }

        return null;
    }

    private function hasRecognizedQuantityNotation(string $part): bool
    {
        if (preg_match('/[xX×]\s*\d+/', $part) === 1) {
            return true;
        }

        if (preg_match('/\s+\d{1,2}(?!\d)/', $part) === 1) {
            return true;
        }

        if (str_contains($part, '|')) {
            return true;
        }

        return false;
    }

    private function missingQuantityWarning(
        string $doctorLabel,
        int $excelRow,
        string $treatmentText,
        string $code,
    ): ImportParseWarningDto {
        return new ImportParseWarningDto(
            excelRow: $excelRow,
            doctor: $doctorLabel,
            treatmentText: $treatmentText,
            warningCode: 'missing_quantity',
            message: "Missing quantity for {$code}. Use {$code} x QUANTITY.",
        );
    }

    private function unknownCodeWarning(
        string $doctorLabel,
        int $excelRow,
        string $treatmentText,
        string $code,
    ): ImportParseWarningDto {
        return new ImportParseWarningDto(
            excelRow: $excelRow,
            doctor: $doctorLabel,
            treatmentText: $treatmentText,
            warningCode: 'unknown_treatment_code',
            message: "Unknown treatment code: {$code}.",
        );
    }

    /** @var Collection<string, Treatment>|null */
    private ?Collection $treatmentCodes = null;

    /**
     * @return Collection<string, Treatment>
     */
    private function getKnownTreatmentCodes(): Collection
    {
        if ($this->treatmentCodes === null) {
            $this->treatmentCodes = Treatment::query()
                ->where('is_active', true)
                ->get()
                ->keyBy('code');
        }

        return $this->treatmentCodes;
    }

    /**
     * @param  array<int, ParsedTreatmentItemDto>  $parsedItems
     * @return array<int, ParsedTreatmentItemDto>
     */
    private function mergeParsedItemsByCode(array $parsedItems): array
    {
        $merged = [];

        foreach ($parsedItems as $item) {
            $code = $item->treatmentCode;

            if (! isset($merged[$code])) {
                $merged[$code] = $item;

                continue;
            }

            $merged[$code] = new ParsedTreatmentItemDto(
                treatmentCode: $code,
                quantity: $merged[$code]->quantity + $item->quantity,
                confidence: min($merged[$code]->confidence, $item->confidence),
                warningMessage: $merged[$code]->warningMessage ?? $item->warningMessage,
            );
        }

        return array_values($merged);
    }
}
