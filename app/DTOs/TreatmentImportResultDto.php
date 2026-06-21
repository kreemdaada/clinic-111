<?php

namespace App\DTOs;

/**
 * Result of validating and persisting treatments for one daily work row.
 */
readonly class TreatmentImportResultDto
{
    /**
     * @param  array<int, ImportParseWarningDto>  $warnings
     */
    public function __construct(
        public int $persistedItemCount,
        public array $warnings,
    ) {}
}
