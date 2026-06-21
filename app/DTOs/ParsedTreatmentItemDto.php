<?php

namespace App\DTOs;

/**
 * One treatment line extracted from a daily report `treatment_text` field.
 *
 * Created by {@see \App\Services\Accounting\TreatmentParserService::parse()}.
 * Only items whose code has `has_lab_cost = true` are persisted as work items.
 */
readonly class ParsedTreatmentItemDto
{
    /**
     * @param  string  $treatmentCode  Canonical treatment code (e.g. MC, ZIR, CF).
     * @param  int  $quantity  Number of units (crowns, teeth, implants, etc.).
     * @param  int  $confidence  Parser confidence 0–100; 100 = explicit `CODE x N` notation.
     * @param  string|null  $warningMessage  Human-readable note when quantity was inferred from tooth notation.
     */
    public function __construct(
        public string $treatmentCode,
        public int $quantity,
        public int $confidence,
        public ?string $warningMessage = null,
    ) {}
}
