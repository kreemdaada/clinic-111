<?php

namespace App\Console\Commands;

use App\Services\Database\PostgresMigrationValidationService;
use App\Services\Database\SqliteToPostgresMigrationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * Migrate business data from SQLite (dev) to PostgreSQL (production).
 *
 * Source SQLite is read-only — never deleted or overwritten.
 */
class MigrateSqliteToPgsqlCommand extends Command
{
    protected $signature = 'app:migrate-sqlite-to-pgsql
                            {--sqlite=database/database.sqlite : Path to source SQLite database file}
                            {--pgsql=pgsql : Target PostgreSQL connection name from config/database.php}
                            {--dry-run : Show row counts only; no writes to PostgreSQL}
                            {--force : Import even when target already has business rows}';

    protected $description = 'Copy clinic business data from SQLite into PostgreSQL (preserves IDs; never deletes source)';

    public function __construct(
        private readonly SqliteToPostgresMigrationService $migrationService,
        private readonly PostgresMigrationValidationService $validationService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $pgsqlConnection = (string) $this->option('pgsql');

        try {
            $sqlitePath = $this->resolveSqlitePath((string) $this->option('sqlite'));
        } catch (RuntimeException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->components->info('SQLite → PostgreSQL data migration');
        $this->line("  Source file: {$sqlitePath}");
        $this->line("  Target connection: {$pgsqlConnection}");
        $this->line('  Mode: '.($dryRun ? 'DRY RUN (no writes)' : 'IMPORT'));
        $this->newLine();
        $this->warn('Ensure you have a backup before importing:');
        $this->line('  cp database/database.sqlite database/database.sqlite.backup-$(date +%Y%m%d-%H%M%S)');
        $this->line('  pg_dump <database> > backup.sql');
        $this->newLine();

        try {
            $source = $this->sqliteConnection($sqlitePath);
            $target = DB::connection($pgsqlConnection);

            $result = $this->migrationService->run(
                $source,
                $target,
                dryRun: $dryRun,
                force: (bool) $this->option('force'),
            );

            $this->renderCountTable('Source (SQLite)', $result['source']['counts']);
            $this->renderCountTable('Target (PostgreSQL)', $result['target']['counts']);

            if ($dryRun) {
                $this->newLine();
                $this->components->info('Dry run complete. No data was written.');
                $this->line('Next steps:');
                $this->line('  1. php artisan migrate --database='.$pgsqlConnection.' --force');
                $this->line('  2. php artisan app:migrate-sqlite-to-pgsql --pgsql='.$pgsqlConnection);

                return self::SUCCESS;
            }

            $this->newLine();
            $this->components->info('Imported row counts');
            foreach ($result['imported'] as $table => $count) {
                $this->line(sprintf('  %-35s %d', $table, $count));
            }

            $this->newLine();
            $this->renderValidation($result['validation']);

            if ($result['validation']['passed']) {
                $this->components->info('Migration validation passed.');
            } else {
                $this->components->error('Migration validation failed.');

                return self::FAILURE;
            }

            return self::SUCCESS;
        } catch (RuntimeException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        } catch (Throwable $exception) {
            $this->components->error('Migration failed: '.$exception->getMessage());

            return self::FAILURE;
        }
    }

    private function resolveSqlitePath(string $path): string
    {
        $absolute = str_starts_with($path, DIRECTORY_SEPARATOR)
            ? $path
            : base_path($path);

        if (! is_file($absolute)) {
            throw new RuntimeException("SQLite file not found: {$absolute}");
        }

        return $absolute;
    }

    private function sqliteConnection(string $absolutePath)
    {
        Config::set('database.connections.sqlite_migration', [
            'driver' => 'sqlite',
            'url' => null,
            'database' => $absolutePath,
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);

        return DB::connection('sqlite_migration');
    }

    /**
     * @param  array<string, int>  $counts
     */
    private function renderCountTable(string $title, array $counts): void
    {
        $this->components->info($title);

        $rows = [];

        foreach ($counts as $table => $count) {
            $rows[] = [$table, (string) $count];
        }

        $this->table(['Table', 'Rows'], $rows);
    }

    /**
     * @param  array<string, mixed>  $validation
     */
    private function renderValidation(array $validation): void
    {
        $this->components->info('Validation summary');

        $aggregateRows = [];

        foreach ($validation['aggregates'] as $key => $row) {
            $aggregateRows[] = [
                $key,
                $row['source'],
                $row['target'],
                $row['match'] ? 'OK' : 'MISMATCH',
            ];
        }

        $this->table(['Aggregate', 'Source', 'Target', 'Status'], $aggregateRows);

        $mismatched = collect($validation['table_counts'])
            ->filter(fn (array $row) => ! $row['match'])
            ->keys()
            ->all();

        if ($mismatched !== []) {
            $this->warn('Row count mismatches: '.implode(', ', $mismatched));
        }
    }
}
