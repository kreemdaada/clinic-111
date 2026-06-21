<?php

namespace App\Services\Accounting;

use App\DTOs\ParsedTreatmentItemDto;
use App\Models\DailyWorkRow;
use App\Models\Treatment;
use App\Models\WorkItem;
use Illuminate\Support\Collection;

/**
 * Rule-based parser for treatment_text fields from daily work rows.
 * Parses each patient segment separately (split on " | ").
 *
 * Clinic notation for fillings (CF, RCF, SxP):
 * - CFx2 / CF x3        → explicit quantity
 * - CF 45 / CF 876      → tooth digits → quantity (2 and 3)
 * - CF 2|5 |4           → teeth 2,5,4 → quantity 3
 * - RCF 321|12          → teeth 3,2,1 + 1,2 → quantity 5
 * - RCF |6 / CF |7      → single tooth → quantity 1
 * - SxP + CF 876        → SxP x1 + CF x3
 *
 * Crown / lab codes (MC, ZIR, …):
 * - MC CR 8765|5678     → upper + lower teeth → quantity 8
 * - ZIR CR 546|5        → tooth list + explicit quantity 5
 * - MC x8               → explicit quantity (preferred from next month)
 */
class TreatmentParserService
{
    private const MAX_QUANTITY = 50;

    /** @var array<int, string> */
    private const FILLING_CODES = ['SXP', 'RCF', 'CF', 'AF'];

    /** @var array<string, string> */
    private const CODE_ALIASES = [
        'ZIR CR' => 'ZIR',
        'ZIRCR' => 'ZIR',
        'ZIR BR' => 'ZIR',
        'IMPL CR' => 'IMPL-CR',
        'IMPL-CR' => 'IMPL-CR',
        'IMP-CR' => 'IMPL-CR',
        'IMPL-ZIR' => 'IMPL-ZIR',
        'IMPL ZIR' => 'IMPL-ZIR',
        'IMP' => 'IMPL',
        'REPEAR' => 'REPAIR',
        'RE-PEAR' => 'REPAIR',
        'RERCT' => 'RE-RCT',
        'RE-RCT' => 'RE-RCT',
        'SXP' => 'SXP',
        'ABB' => 'ABT',
        'ABBT' => 'ABT',
        'VENEER' => 'VENEER',
        'BLEACHING' => 'BLEACHING',
        'BLEACH' => 'BLEACHING',
        'EXO' => 'EXO',
        'APICO' => 'APICO',
        'APICECTOMY' => 'APICO',
    ];

    /** @var array<int, string> */
    private const CROWN_PIPE_QUANTITY_CODES = ['MC', 'ZIR', 'POST', 'ABT', 'IMPL-CR', 'IMPL-ZIR'];

    /** @var Collection<string, Treatment>|null */
    private ?Collection $treatmentCodes = null;

    /**
     * Parse treatment text into a list of treatment codes with quantities.
     *
     * Splits on ` | ` patient segments and merges duplicate codes.
     *
     * @param  string  $treatmentText  Raw treatment text from a daily work row.
     * @return array<int, ParsedTreatmentItemDto> Parsed items keyed by merge order.
     */
    public function parse(string $treatmentText): array
    {
        if (trim($treatmentText) === '') {
            return [];
        }

        $segments = preg_split('/\s\|\s/', $treatmentText) ?: [$treatmentText];
        $segments = $this->mergeToothContinuationSegments($segments);
        $parsedItems = [];

        foreach ($segments as $segment) {
            $segment = trim($segment);

            if ($segment === '') {
                continue;
            }

            $parsedItems = array_merge(
                $parsedItems,
                $this->parseSegment($segment),
            );
        }

        return $this->mergeParsedItemsByCode($parsedItems);
    }

    /**
     * Parse treatment text and persist lab-cost work items for a daily work row.
     *
     * Deletes existing work items first; skips treatments without lab cost.
     *
     * @param  DailyWorkRow  $dailyWorkRow  Row whose treatment_text is parsed.
     */
    public function parseAndPersist(DailyWorkRow $dailyWorkRow): void
    {
        $dailyWorkRow->workItems()->delete();

        if (blank($dailyWorkRow->treatment_text)) {
            return;
        }

        $parsedItems = $this->parse($dailyWorkRow->treatment_text);
        $treatmentsByCode = $this->getKnownTreatmentCodes();

        foreach ($parsedItems as $parsedItem) {
            $treatment = $treatmentsByCode->get($parsedItem->treatmentCode);

            if ($treatment === null || ! $treatment->has_lab_cost) {
                continue;
            }

            WorkItem::query()->create([
                'daily_work_row_id' => $dailyWorkRow->id,
                'treatment_id' => $treatment->id,
                'quantity' => $parsedItem->quantity,
                'confidence' => $parsedItem->confidence,
                'warning_message' => $parsedItem->warningMessage,
            ]);
        }
    }

    /**
     * Merge standalone digit segments into the previous segment as pipe notation.
     *
     * Handles continuations like `CF 876` followed by `|5` split across segments.
     *
     * @param  array<int, string>  $segments  Patient segments from splitting on ` | `.
     * @return array<int, string> Segments with tooth continuations merged.
     */
    private function mergeToothContinuationSegments(array $segments): array
    {
        $merged = [];

        foreach ($segments as $segment) {
            $trimmed = trim($segment);

            if (preg_match('/^\d+$/', $trimmed) && $merged !== []) {
                $merged[count($merged) - 1] .= '|'.$trimmed;

                continue;
            }

            $merged[] = $segment;
        }

        return $merged;
    }

    /**
     * Parse one patient segment split on `+` into filling and non-filling items.
     *
     * @param  string  $segment  Single patient treatment segment.
     * @return array<int, ParsedTreatmentItemDto> Parsed items from this segment.
     */
    private function parseSegment(string $segment): array
    {
        $normalizedSegment = $this->normalizeTreatmentText($segment);
        $parts = preg_split('/\s*\+\s*/', $normalizedSegment) ?: [$normalizedSegment];
        $parsedItems = [];

        foreach ($parts as $part) {
            $part = trim($part);

            if ($part === '') {
                continue;
            }

            $parsedItems = array_merge(
                $parsedItems,
                $this->parseFillingPart($part),
                $this->parseNonFillingPart($part),
            );
        }

        return $parsedItems;
    }

    /**
     * Parse filling codes (CF, RCF, SxP, AF) from a treatment part using tooth notation.
     *
     * @param  string  $part  Normalized sub-segment (one `+`-delimited piece).
     * @return array<int, ParsedTreatmentItemDto> Filling items found in the part.
     */
    private function parseFillingPart(string $part): array
    {
        $part = preg_replace('/\s*\|\s*/', '|', $part) ?? $part;
        $parsedItems = [];

        foreach (self::FILLING_CODES as $code) {
            if (! $this->getKnownTreatmentCodes()->has($code)) {
                continue;
            }

            $item = $this->matchFillingCode($code, $part);

            if ($item !== null) {
                $parsedItems[] = $item;
            }
        }

        return $parsedItems;
    }

    /**
     * Match a single filling code against clinic tooth-notation patterns in a part.
     *
     * @param  string  $code  Filling treatment code (CF, RCF, SXP, AF).
     * @param  string  $part  Normalized sub-segment to search.
     * @return ParsedTreatmentItemDto|null Parsed item, or null when the code is not found.
     */
    private function matchFillingCode(string $code, string $part): ?ParsedTreatmentItemDto
    {
        $codePattern = preg_quote($code, '/');

        if (preg_match('/\b'.$codePattern.'\b\s*[xX×]\s*(\d+)/', $part, $matches) === 1) {
            return $this->fillingItem($code, (int) $matches[1], 100);
        }

        if (preg_match('/\b'.$codePattern.'[xX×](\d+)/', $part, $matches) === 1) {
            return $this->fillingItem($code, (int) $matches[1], 100);
        }

        if (preg_match('/\b'.$codePattern.'\b\s*\|\s*(\d+)/', $part, $matches) === 1) {
            return $this->fillingItem($code, $this->interpretPipeDigits($matches[1]), 85);
        }

        if (preg_match('/\b'.$codePattern.'\s*\|\s*(\d+)/', $part, $matches) === 1) {
            return $this->fillingItem($code, $this->interpretPipeDigits($matches[1]), 85);
        }

        if (preg_match('/\b'.$codePattern.'\b\s+([\d|]+)/', $part, $matches) === 1) {
            $quantity = $this->countTeethFromPipeGroups($matches[1]);

            return $this->fillingItem($code, $quantity, 85);
        }

        if (preg_match('/\b'.$codePattern.'\b(?!\s*[xX×0-9|\s])/i', $part) === 1
            && preg_match('/\b'.$codePattern.'\b/', $part) === 1) {
            return $this->fillingItem($code, 1, 85);
        }

        return null;
    }

    /**
     * Build a filling ParsedTreatmentItemDto with clamped quantity and confidence warning.
     *
     * @param  string  $code  Treatment code.
     * @param  int  $quantity  Raw quantity before clamping.
     * @param  int  $confidence  Match confidence (100 = explicit, lower = inferred).
     * @return ParsedTreatmentItemDto Parsed item with optional warning message.
     */
    private function fillingItem(string $code, int $quantity, int $confidence): ParsedTreatmentItemDto
    {
        return new ParsedTreatmentItemDto(
            treatmentCode: $code,
            quantity: max(1, min(self::MAX_QUANTITY, $quantity)),
            confidence: $confidence,
            warningMessage: $confidence < 100 ? "Treatment {$code} quantity inferred from clinic tooth notation." : null,
        );
    }

    /**
     * Parse non-filling treatment codes from a part using longest-match-first scanning.
     *
     * @param  string  $part  Normalized sub-segment (one `+`-delimited piece).
     * @return array<int, ParsedTreatmentItemDto> Crown, implant, and other non-filling items.
     */
    private function parseNonFillingPart(string $part): array
    {
        $knownCodes = $this->getKnownTreatmentCodes();
        $sortedCodes = $knownCodes->keys()
            ->reject(fn (string $code) => in_array($code, self::FILLING_CODES, true))
            ->sortByDesc(fn (string $code) => strlen($code))
            ->values();

        $parsedItems = [];
        $matchedRanges = [];

        foreach ($sortedCodes as $code) {
            $pattern = '/\b'.preg_quote($code, '/').'\b(?:\s*[xX×]\s*(\d+)|\s*\((\d+)\)|\s+(\d{1,2})(?!\d)(?!\s*\|)(?!\|))?/';

            if (! preg_match_all($pattern, $part, $matches, PREG_OFFSET_CAPTURE)) {
                continue;
            }

            foreach ($matches[0] as $index => $match) {
                $matchText = $match[0];
                $matchOffset = $match[1];
                $matchEnd = $matchOffset + strlen($matchText);

                if ($this->overlapsExistingMatch($matchOffset, $matchEnd, $matchedRanges)) {
                    continue;
                }

                $quantity = $this->resolveQuantityFromMatch(
                    $code,
                    $part,
                    $matchText,
                    $matches,
                    $index,
                );

                $confidence = 100;
                $warningMessage = null;

                if ($quantity === 1 && ! str_contains($matchText, 'X') && ! str_contains($matchText, '×') && ! preg_match('/\(\d+\)/', $matchText)) {
                    $confidence = 85;
                    $warningMessage = "Treatment {$code} detected; quantity inferred from clinic notation.";
                }

                $parsedItems[] = new ParsedTreatmentItemDto(
                    treatmentCode: $code,
                    quantity: $quantity,
                    confidence: $confidence,
                    warningMessage: $warningMessage,
                );

                $matchedRanges[] = [$matchOffset, $matchEnd];
            }
        }

        return $parsedItems;
    }

    /**
     * Resolve treatment quantity from explicit match groups or pipe/tooth notation.
     *
     * @param  string  $code  Matched treatment code.
     * @param  string  $normalizedSegment  Full normalized segment text.
     * @param  string  $matchText  Regex match substring.
     * @param  array<int, array<int, array{0: string, 1: int}>>  $matches  preg_match_all capture groups.
     * @param  int  $index  Index of the current match within $matches[0].
     * @return int Quantity clamped to 1..MAX_QUANTITY.
     */
    private function resolveQuantityFromMatch(
        string $code,
        string $normalizedSegment,
        string $matchText,
        array $matches,
        int $index,
    ): int {
        foreach ([1, 2, 3] as $groupIndex) {
            $quantityString = $matches[$groupIndex][$index][0] ?? '';

            if ($quantityString !== '' && is_numeric($quantityString)) {
                return max(1, min(self::MAX_QUANTITY, (int) $quantityString));
            }
        }

        $pipeQuantity = $this->resolvePipeNotationQuantity($code, $normalizedSegment);

        if ($pipeQuantity !== null) {
            return $pipeQuantity;
        }

        return 1;
    }

    /**
     * Derive quantity from pipe/tooth-list notation following a treatment code.
     *
     * @param  string  $code  Treatment code preceding the tooth list.
     * @param  string  $normalizedSegment  Full normalized segment text.
     * @return int|null Resolved quantity, or null when no pipe notation applies.
     */
    private function resolvePipeNotationQuantity(string $code, string $normalizedSegment): ?int
    {
        $codePattern = preg_quote($code, '/');

        if (preg_match('/\b'.$codePattern.'\b(?:\s+(?:CR|BR))?\s+([\d|]+)/', $normalizedSegment, $toothListMatch) === 1) {
            $toothGroups = trim($toothListMatch[1], '|');

            if ($toothGroups === '') {
                return null;
            }

            if (str_contains($toothGroups, '|')) {
                $parts = array_values(array_filter(explode('|', $toothGroups), fn (string $part): bool => $part !== ''));
                $lastPart = $parts[array_key_last($parts)] ?? '';

                // ZIR CR 546|5 → explicit quantity 5 (not tooth "5" only)
                if (count($parts) === 2 && strlen($lastPart) <= 2 && (int) $lastPart >= 1 && (int) $lastPart <= self::MAX_QUANTITY) {
                    return (int) $lastPart;
                }

                return $this->countTeethFromPipeGroups($toothGroups);
            }

            return $this->countTeethFromPipeGroups($toothGroups);
        }

        if (preg_match('/\b'.$codePattern.'\b[^|]*(\d+)\|\s*(?:\s+\+|$)/', $normalizedSegment, $trailingToothMatch) === 1) {
            return $this->interpretTrailingPipeQuantity($code, $trailingToothMatch[1]);
        }

        if (preg_match('/\b'.$codePattern.'\b\s*\|\s*(\d+)(?:\s|$|\+)/', $normalizedSegment, $directPipeMatch) === 1) {
            return $this->interpretDirectPipeQuantity($code, $directPipeMatch[1]);
        }

        if (preg_match('/\b'.$codePattern.'\b[^|]+\|\s*(\d+)(?:\s|$|\+)/', $normalizedSegment, $pipeMatch) === 1) {
            return $this->interpretPipeDigits($pipeMatch[1]);
        }

        return null;
    }

    /**
     * Count total teeth across pipe-separated digit groups.
     *
     * @param  string  $toothGroups  Tooth digits with `|` group separators.
     * @return int Total tooth count, clamped to MAX_QUANTITY.
     */
    private function countTeethFromPipeGroups(string $toothGroups): int
    {
        $groups = preg_split('/\|/', $toothGroups) ?: [$toothGroups];
        $total = 0;

        foreach ($groups as $group) {
            $digits = preg_replace('/\D/', '', $group) ?? '';

            if ($digits === '') {
                continue;
            }

            $total += $this->countToothDigits($digits);
        }

        return max(1, min(self::MAX_QUANTITY, $total));
    }

    /**
     * Count teeth represented by a digit string (FDI pairs or individual digits).
     *
     * @param  string  $digits  Digit sequence from tooth notation.
     * @return int Tooth count (minimum 1).
     */
    private function countToothDigits(string $digits): int
    {
        $digits = trim($digits);

        if ($digits === '') {
            return 1;
        }

        if (preg_match('/^[1-8]+$/', $digits) === 1) {
            return strlen($digits);
        }

        return $this->interpretPipeDigits($digits);
    }

    /**
     * Interpret a single-digit pipe quantity for crown/lab codes.
     *
     * @param  string  $code  Treatment code (MC, ZIR, etc.).
     * @param  string  $digits  Single digit after `|`.
     * @return int Resolved quantity.
     */
    private function interpretDirectPipeQuantity(string $code, string $digits): int
    {
        if (in_array($code, self::CROWN_PIPE_QUANTITY_CODES, true) && strlen($digits) === 1) {
            return max(1, min(self::MAX_QUANTITY, (int) $digits));
        }

        return $this->interpretPipeDigits($digits);
    }

    /**
     * Interpret trailing pipe digits as explicit quantity for crown/lab codes.
     *
     * @param  string  $code  Treatment code (MC, ZIR, etc.).
     * @param  string  $digits  Trailing digits after a pipe.
     * @return int Resolved quantity.
     */
    private function interpretTrailingPipeQuantity(string $code, string $digits): int
    {
        if (in_array($code, self::CROWN_PIPE_QUANTITY_CODES, true)) {
            $quantityCandidate = (int) $digits;

            if ($quantityCandidate >= 1 && $quantityCandidate <= self::MAX_QUANTITY) {
                return $quantityCandidate;
            }
        }

        return $this->countToothDigits($digits);
    }

    /**
     * Interpret a digit string as tooth count using FDI and multi-tooth heuristics.
     *
     * @param  string  $digits  Raw digit sequence from pipe notation.
     * @return int Inferred quantity (minimum 1).
     */
    private function interpretPipeDigits(string $digits): int
    {
        $digits = trim($digits);

        if ($digits === '') {
            return 1;
        }

        if (strlen($digits) === 1) {
            return 1;
        }

        if (strlen($digits) === 2) {
            $toothNumber = (int) $digits;

            if ($toothNumber >= 11 && $toothNumber <= 48) {
                return 1;
            }

            if ($digits[0] >= '1' && $digits[0] <= '8' && $digits[1] >= '1' && $digits[1] <= '8') {
                return 2;
            }
        }

        if (preg_match('/^[1-8]+$/', $digits) === 1) {
            return strlen($digits);
        }

        return 1;
    }

    /**
     * Uppercase, alias-expand, and normalize treatment text before parsing.
     *
     * @param  string  $treatmentText  Raw treatment text segment.
     * @return string Normalized text ready for regex matching.
     */
    private function normalizeTreatmentText(string $treatmentText): string
    {
        $normalized = strtoupper($treatmentText);
        $normalized = str_replace(['×'], 'X', $normalized);

        $normalized = preg_replace('/\bDEEP\s+SXP\b/', 'SXP', $normalized) ?? $normalized;

        foreach (self::CODE_ALIASES as $alias => $canonicalCode) {
            $normalized = preg_replace(
                '/\b'.preg_quote($alias, '/').'\b/i',
                $canonicalCode,
                $normalized,
            ) ?? $normalized;
        }

        return $normalized;
    }

    /**
     * Merge parsed items that share the same treatment code by summing quantities.
     *
     * Keeps the lower confidence and existing warning message on merge.
     *
     * @param  array<int, ParsedTreatmentItemDto>  $parsedItems  Items from all segments.
     * @return array<int, ParsedTreatmentItemDto> De-duplicated items by code.
     */
    private function mergeParsedItemsByCode(array $parsedItems): array
    {
        $merged = [];

        foreach ($parsedItems as $parsedItem) {
            if (! array_key_exists($parsedItem->treatmentCode, $merged)) {
                $merged[$parsedItem->treatmentCode] = $parsedItem;

                continue;
            }

            $existing = $merged[$parsedItem->treatmentCode];
            $merged[$parsedItem->treatmentCode] = new ParsedTreatmentItemDto(
                treatmentCode: $parsedItem->treatmentCode,
                quantity: min(self::MAX_QUANTITY, $existing->quantity + $parsedItem->quantity),
                confidence: min($existing->confidence, $parsedItem->confidence),
                warningMessage: $existing->warningMessage,
            );
        }

        return array_values($merged);
    }

    /**
     * Load and cache active treatment records keyed by code.
     *
     * @return Collection<string, Treatment> Code → Treatment model.
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
     * Check whether a regex match range overlaps an already-matched span.
     *
     * @param  int  $offset  Start byte offset of the new match.
     * @param  int  $end  End byte offset of the new match.
     * @param  array<int, array{0: int, 1: int}>  $matchedRanges  Previously recorded [start, end] pairs.
     * @return bool True when the new match overlaps an existing range.
     */
    private function overlapsExistingMatch(int $offset, int $end, array $matchedRanges): bool
    {
        foreach ($matchedRanges as [$existingOffset, $existingEnd]) {
            if ($offset < $existingEnd && $end > $existingOffset) {
                return true;
            }
        }

        return false;
    }
}
