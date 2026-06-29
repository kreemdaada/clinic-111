<?php

namespace App\Support\Database;

/**
 * Business tables and import order for SQLite → PostgreSQL data migration.
 *
 * Order respects foreign-key dependencies. Primary keys are preserved on import.
 */
final class SqliteToPostgresTableRegistry
{
    /** @var list<string> */
    public const IMPORT_ORDER = [
        'clinics',
        'users',
        'labs',
        'treatments',
        'doctors',
        'lab_prices',
        'doctor_fixed_fees',
        'doctor_lab_billings',
        'doctor_income_export_profiles',
        'daily_reports',
        'daily_work_rows',
        'payments',
        'work_items',
        'lab_jobs',
        'daily_report_import_warnings',
        'audit_logs',
    ];

    /** @var list<string> */
    public const TRUNCATE_ORDER = [
        'audit_logs',
        'daily_report_import_warnings',
        'lab_jobs',
        'work_items',
        'payments',
        'daily_work_rows',
        'daily_reports',
        'doctor_income_export_profiles',
        'doctor_lab_billings',
        'doctor_fixed_fees',
        'lab_prices',
        'doctors',
        'treatments',
        'labs',
        'users',
        'clinics',
    ];

    /** @var array<string, list<string>> Columns stored as boolean in PostgreSQL, keyed by table. */
    private const BOOLEAN_COLUMNS = [
        'clinics' => ['is_active'],
        'users' => ['is_active'],
        'labs' => ['is_active'],
        'treatments' => ['has_lab_cost', 'is_active'],
        'doctors' => ['is_active'],
        'lab_prices' => ['is_active'],
        'doctor_fixed_fees' => ['is_active'],
        'doctor_income_export_profiles' => ['write_payment_headers', 'summary_shows_net_total'],
    ];

    /**
     * @return list<string>
     */
    public static function importOrder(): array
    {
        return self::IMPORT_ORDER;
    }

    /**
     * @return list<string>
     */
    public static function truncateOrder(): array
    {
        return self::TRUNCATE_ORDER;
    }

    /**
     * @return list<string>
     */
    public static function booleanColumns(string $table): array
    {
        /** @var array<string, list<string>> $map */
        $map = self::BOOLEAN_COLUMNS;

        return $map[$table] ?? [];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    public static function normalizeRow(string $table, array $row): array
    {
        foreach (self::booleanColumns($table) as $column) {
            if (! array_key_exists($column, $row)) {
                continue;
            }

            $row[$column] = filter_var($row[$column], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? (bool) $row[$column];
        }

        return $row;
    }
}
