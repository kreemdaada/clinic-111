<?php

namespace App\DTOs;

use App\Models\Nurse;

/**
 * Result of resolving a nurse alias during Excel import.
 */
final class NurseImportResolution
{
    public function __construct(
        public readonly ?Nurse $nurse,
        public readonly ?string $warningCode,
        public readonly ?string $message,
    ) {}

    public static function matched(Nurse $nurse): self
    {
        return new self($nurse, null, null);
    }

    public static function none(): self
    {
        return new self(null, null, null);
    }

    public static function unresolved(string $alias): self
    {
        return new self(
            null,
            'nurse_not_found',
            "Nurse \"{$alias}\" was not found for this clinic.",
        );
    }

    public static function inactive(Nurse $nurse): self
    {
        return new self(
            null,
            'nurse_inactive',
            "Nurse \"{$nurse->name}\" is inactive and cannot be assigned to new OPG rows.",
        );
    }

    public static function ambiguous(string $alias): self
    {
        return new self(
            null,
            'nurse_ambiguous',
            "Multiple active nurses match \"{$alias}\". Resolve manually in the daily report editor.",
        );
    }
}
