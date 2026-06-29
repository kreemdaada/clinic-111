<?php

namespace App\Services\Database;

use App\Support\Database\SqliteToPostgresTableRegistry;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Compares row counts and accounting aggregates between SQLite source and PostgreSQL target.
 */
class PostgresMigrationValidationService
{
    /**
     * @return array{
     *     table_counts: array<string, array{source: int, target: int, match: bool}>,
     *     aggregates: array<string, array{source: string, target: string, match: bool}>,
     *     passed: bool,
     * }
     */
    public function validate(Connection $source, Connection $target): array
    {
        $tableCounts = [];
        $allCountsMatch = true;

        foreach (SqliteToPostgresTableRegistry::importOrder() as $table) {
            if (! $this->tableExists($source, $table) || ! $this->tableExists($target, $table)) {
                throw new RuntimeException("Table {$table} is missing on source or target connection.");
            }

            $sourceCount = (int) $source->table($table)->count();
            $targetCount = (int) $target->table($table)->count();
            $match = $sourceCount === $targetCount;
            $allCountsMatch = $allCountsMatch && $match;

            $tableCounts[$table] = [
                'source' => $sourceCount,
                'target' => $targetCount,
                'match' => $match,
            ];
        }

        $aggregates = [
            'payments_amount_aed_sum' => $this->compareSum($source, $target, 'payments', 'amount_aed'),
            'lab_jobs_total_cost_sum' => $this->compareSum($source, $target, 'lab_jobs', 'total_cost_aed'),
            'daily_work_rows_paid_total_sum' => $this->compareSum($source, $target, 'daily_work_rows', 'paid_total_aed'),
        ];

        $allAggregatesMatch = collect($aggregates)->every(fn (array $row) => $row['match']);

        return [
            'table_counts' => $tableCounts,
            'aggregates' => $aggregates,
            'passed' => $allCountsMatch && $allAggregatesMatch,
        ];
    }

    /**
     * @return array{source: int, target: int, match: bool}
     */
    public function countSummary(Connection $connection, string $connectionLabel): array
    {
        $counts = [];

        foreach (SqliteToPostgresTableRegistry::importOrder() as $table) {
            if (! $this->tableExists($connection, $table)) {
                $counts[$table] = 0;

                continue;
            }

            $counts[$table] = (int) $connection->table($table)->count();
        }

        return [
            'label' => $connectionLabel,
            'counts' => $counts,
            'clinics' => $counts['clinics'] ?? 0,
            'users' => $counts['users'] ?? 0,
            'daily_reports' => $counts['daily_reports'] ?? 0,
            'audit_logs' => $counts['audit_logs'] ?? 0,
        ];
    }

    /**
     * @return array{source: string, target: string, match: bool}
     */
    private function compareSum(Connection $source, Connection $target, string $table, string $column): array
    {
        $sourceSum = $this->sumColumn($source, $table, $column);
        $targetSum = $this->sumColumn($target, $table, $column);

        return [
            'source' => $sourceSum,
            'target' => $targetSum,
            'match' => bccomp($sourceSum, $targetSum, 2) === 0,
        ];
    }

    private function sumColumn(Connection $connection, string $table, string $column): string
    {
        if (! $this->tableExists($connection, $table)) {
            return '0.00';
        }

        $sum = $connection->table($table)->sum($column);

        return number_format((float) ($sum ?? 0), 2, '.', '');
    }

    private function tableExists(Connection $connection, string $table): bool
    {
        return $connection->getSchemaBuilder()->hasTable($table);
    }
}
