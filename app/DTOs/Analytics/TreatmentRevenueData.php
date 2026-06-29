<?php

namespace App\DTOs\Analytics;

/**
 * Treatment revenue after quantity-weighted allocation from row payments.
 */
readonly class TreatmentRevenueData
{
    public function __construct(
        public int $treatmentId,
        public string $code,
        public string $name,
        public string $revenue,
    ) {}
}
