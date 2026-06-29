<?php

namespace App\Services\Database;

use App\Support\Database\SqliteToPostgresTableRegistry;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * Copies business data from a SQLite file into a migrated PostgreSQL database.
 *
 * Never modifies or deletes the source SQLite file.
 */
class SqliteToPostgresMigrationService
{
    private const CHUNK_SIZE = 250;

    public function __construct(
        private readonly PostgresMigrationValidationService $validationService,
    ) {}

    /**
     * @return array{
     *     dry_run: bool,
     *     source: array<string, mixed>,
     *     target: array<string, mixed>,
     *     imported: array<string, int>|null,
     *     validation: array<string, mixed>|null,
     * }
     */
    public function run(
        Connection $source,
        Connection $target,
        bool $dryRun = false,
        bool $force = false,
    ): array {
        $this->assertSourceHasBusinessData($source);

        if (! $dryRun) {
            $this->assertPostgreSqlTarget($target);
        }

        $sourceSummary = $this->validationService->countSummary($source, 'sqlite');
        $targetSummary = $this->validationService->countSummary($target, 'pgsql');

        if ($dryRun) {
            return [
                'dry_run' => true,
                'source' => $sourceSummary,
                'target' => $targetSummary,
                'imported' => null,
                'validation' => null,
            ];
        }

        if ($this->targetHasBusinessData($target) && ! $force) {
            throw new RuntimeException(
                'Target PostgreSQL database already contains business data. '
                .'Run migrations on an empty database or pass --force after backup.',
            );
        }

        $imported = [];

        DB::connection($target->getName())->transaction(function () use ($source, $target, &$imported) {
            $this->truncateTargetTables($target);

            foreach (SqliteToPostgresTableRegistry::importOrder() as $table) {
                $imported[$table] = $this->importTable($source, $target, $table);
            }

            $this->resetSequences($target);
        });

        $validation = $this->validationService->validate($source, $target);

        if (! $validation['passed']) {
            throw new RuntimeException('Post-import validation failed. Review counts and aggregates before using PostgreSQL.');
        }

        return [
            'dry_run' => false,
            'source' => $sourceSummary,
            'target' => $targetSummary,
            'imported' => $imported,
            'validation' => $validation,
        ];
    }

    private function assertPostgreSqlTarget(Connection $target): void
    {
        if ($target->getDriverName() !== 'pgsql') {
            throw new RuntimeException('Target connection must use the pgsql driver.');
        }
    }

    private function assertSourceHasBusinessData(Connection $source): void
    {
        if ($source->getDriverName() !== 'sqlite') {
            throw new RuntimeException('Source connection must use the sqlite driver.');
        }

        if (! $source->getSchemaBuilder()->hasTable('clinics')) {
            throw new RuntimeException('Source SQLite file does not contain a clinics table. Is this a clinic database dump?');
        }
    }

    private function targetHasBusinessData(Connection $target): bool
    {
        foreach (SqliteToPostgresTableRegistry::importOrder() as $table) {
            if ($target->getSchemaBuilder()->hasTable($table) && $target->table($table)->exists()) {
                return true;
            }
        }

        return false;
    }

    private function truncateTargetTables(Connection $target): void
    {
        $tables = implode(', ', SqliteToPostgresTableRegistry::truncateOrder());

        $target->statement("TRUNCATE TABLE {$tables} RESTART IDENTITY CASCADE");
    }

    private function importTable(Connection $source, Connection $target, string $table): int
    {
        if (! $source->getSchemaBuilder()->hasTable($table)) {
            return 0;
        }

        $total = 0;
        $query = $source->table($table)->orderBy('id');

        $query->chunkById(self::CHUNK_SIZE, function ($rows) use ($target, $table, &$total) {
            $payload = [];

            foreach ($rows as $row) {
                $payload[] = SqliteToPostgresTableRegistry::normalizeRow($table, (array) $row);
            }

            if ($payload !== []) {
                $target->table($table)->insert($payload);
                $total += count($payload);
            }
        });

        return $total;
    }

    private function resetSequences(Connection $target): void
    {
        foreach (SqliteToPostgresTableRegistry::importOrder() as $table) {
            if (! $target->getSchemaBuilder()->hasTable($table)) {
                continue;
            }

            $maxId = $target->table($table)->max('id');

            if ($maxId === null) {
                continue;
            }

            try {
                $target->statement(
                    "SELECT setval(pg_get_serial_sequence('{$table}', 'id'), ?)",
                    [(int) $maxId],
                );
            } catch (Throwable) {
                // Table may not use a serial id sequence.
            }
        }
    }
}
