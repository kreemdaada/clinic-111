<?php

namespace App\DTOs;

/**
 * Result of idempotent OPG treatment provisioning for one clinic.
 */
readonly class OpgProvisionResult
{
    /**
     * @param  list<string>  $created  Treatment codes newly created.
     * @param  list<string>  $updated  Treatment codes with flags or missing defaults applied.
     * @param  list<string>  $skipped  Treatment codes left unchanged.
     * @param  list<string>  $conflicts  Human-readable conflict messages.
     */
    public function __construct(
        public array $created = [],
        public array $updated = [],
        public array $skipped = [],
        public array $conflicts = [],
    ) {}

    public function hasConflicts(): bool
    {
        return $this->conflicts !== [];
    }
}
