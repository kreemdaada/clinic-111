<?php

namespace App\DTOs;

readonly class ParsedTreatmentItemDto
{
    public function __construct(
        public string $treatmentCode,
        public int $quantity,
        public int $confidence,
        public ?string $warningMessage = null,
    ) {}
}
