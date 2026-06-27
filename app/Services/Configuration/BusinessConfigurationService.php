<?php

namespace App\Services\Configuration;

use App\Exceptions\BusinessConfigurationIncompleteException;

/**
 * Facade for business-configuration readiness checks used by UI and import guards (ADR-031).
 */
class BusinessConfigurationService
{
    public const INCOMPLETE_MESSAGE = 'Your clinic configuration is incomplete. Complete the missing configuration before importing reports.';

    public function __construct(
        private readonly ConfigurationProgressService $configurationProgressService,
    ) {}

    public function canImport(): bool
    {
        return $this->configurationProgressService->readyForImport();
    }

    /**
     * @return array{
     *     steps: list<array<string, mixed>>,
     *     missing_modules: list<string>,
     *     progress_percentage: int,
     *     current_step: string|null,
     *     ready_for_import: bool,
     * }
     */
    public function status(): array
    {
        return $this->configurationProgressService->status();
    }

    /**
     * @throws BusinessConfigurationIncompleteException
     */
    public function assertReadyForImport(): void
    {
        if ($this->canImport()) {
            return;
        }

        throw new BusinessConfigurationIncompleteException(self::INCOMPLETE_MESSAGE);
    }
}
