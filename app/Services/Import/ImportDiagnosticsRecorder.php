<?php

namespace App\Services\Import;

/**
 * In-memory buffer for parser extraction diagnostics (skipped and extracted rows).
 */
final class ImportDiagnosticsRecorder
{
    /** @var array<int, array<string, mixed>> */
    private array $events = [];

    public function reset(): void
    {
        $this->events = [];
    }

    /**
     * @param  array<string, mixed>  $diagnostic
     */
    public function record(array $diagnostic): void
    {
        $this->events[] = $diagnostic;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function all(): array
    {
        return $this->events;
    }
}
