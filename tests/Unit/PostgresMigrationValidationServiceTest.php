<?php

namespace Tests\Unit;

use App\Services\Database\PostgresMigrationValidationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PostgresMigrationValidationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_count_summary_reads_seeded_sqlite_tables(): void
    {
        $this->seed();

        $service = app(PostgresMigrationValidationService::class);
        $summary = $service->countSummary(
            DB::connection(config('database.default')),
            'sqlite',
        );

        $this->assertGreaterThan(0, $summary['clinics']);
        $this->assertGreaterThan(0, $summary['users']);
        $this->assertArrayHasKey('treatments', $summary['counts']);
    }
}
