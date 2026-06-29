<?php

namespace Tests\Unit;

use App\Support\Database\SqliteToPostgresTableRegistry;
use Tests\TestCase;

class SqliteToPostgresTableRegistryTest extends TestCase
{
    public function test_import_order_lists_clinics_before_dependent_tables(): void
    {
        $order = SqliteToPostgresTableRegistry::importOrder();

        $this->assertSame('clinics', $order[0]);
        $this->assertContains('users', $order);
        $this->assertContains('daily_reports', $order);
        $this->assertContains('audit_logs', $order);

        $positions = array_flip($order);

        $this->assertTrue($positions['labs'] < $positions['doctors']);
        $this->assertTrue($positions['clinics'] < $positions['doctors']);
        $this->assertTrue($positions['daily_work_rows'] < $positions['payments']);
        $this->assertTrue($positions['daily_reports'] < $positions['daily_work_rows']);
    }

    public function test_truncate_order_is_reverse_dependency_order(): void
    {
        $import = SqliteToPostgresTableRegistry::importOrder();
        $truncate = SqliteToPostgresTableRegistry::truncateOrder();

        $this->assertSame('clinics', $import[0]);
        $this->assertSame('clinics', $truncate[array_key_last($truncate)]);
        $this->assertSame('audit_logs', $truncate[0]);
    }

    public function test_normalize_row_converts_sqlite_boolean_integers(): void
    {
        $row = SqliteToPostgresTableRegistry::normalizeRow('labs', [
            'id' => 1,
            'is_active' => 1,
            'name' => 'Main Lab',
        ]);

        $this->assertTrue($row['is_active']);

        $row = SqliteToPostgresTableRegistry::normalizeRow('labs', [
            'id' => 1,
            'is_active' => 0,
            'name' => 'Main Lab',
        ]);

        $this->assertFalse($row['is_active']);
    }
}
