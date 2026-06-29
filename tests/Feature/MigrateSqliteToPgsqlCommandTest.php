<?php

namespace Tests\Feature;

use App\Console\Commands\MigrateSqliteToPgsqlCommand;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class MigrateSqliteToPgsqlCommandTest extends TestCase
{
    public function test_dry_run_against_default_sqlite_reports_source_counts(): void
    {
        $this->seed();

        $exitCode = Artisan::call('app:migrate-sqlite-to-pgsql', [
            '--sqlite' => database_path('testing.sqlite'),
            '--pgsql' => 'sqlite',
            '--dry-run' => true,
        ]);

        $this->assertSame(MigrateSqliteToPgsqlCommand::SUCCESS, $exitCode);

        $output = Artisan::output();

        $this->assertStringContainsString('DRY RUN', $output);
        $this->assertStringContainsString('clinics', $output);
        $this->assertStringContainsString('No data was written', $output);
    }

    public function test_command_fails_when_sqlite_file_missing(): void
    {
        $exitCode = Artisan::call('app:migrate-sqlite-to-pgsql', [
            '--sqlite' => 'database/does-not-exist.sqlite',
            '--dry-run' => true,
        ]);

        $this->assertSame(MigrateSqliteToPgsqlCommand::FAILURE, $exitCode);
        $this->assertStringContainsString('not found', Artisan::output());
    }

    public function test_import_rejects_non_pgsql_target_connection(): void
    {
        $this->seed();

        $exitCode = Artisan::call('app:migrate-sqlite-to-pgsql', [
            '--sqlite' => database_path('testing.sqlite'),
            '--pgsql' => 'sqlite',
        ]);

        $this->assertSame(MigrateSqliteToPgsqlCommand::FAILURE, $exitCode);
        $this->assertStringContainsString('pgsql driver', Artisan::output());
    }
}
