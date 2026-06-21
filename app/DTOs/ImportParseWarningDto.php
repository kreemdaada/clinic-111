<?php

namespace App\DTOs;

/**
 * One parser/import warning for a daily work row treatment line.
 */
readonly class ImportParseWarningDto
{
    public function __construct(
        public int $excelRow,
        public string $doctor,
        public ?string $treatmentText,
        public string $warningCode,
        public string $message,
    ) {}
}
