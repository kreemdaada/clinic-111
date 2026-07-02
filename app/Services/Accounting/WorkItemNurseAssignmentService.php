<?php

namespace App\Services\Accounting;

use App\Models\DailyWorkRow;
use App\Models\WorkItem;
use App\Support\AccountingScopedQuery;

/**
 * Applies nurse_id from daily work row raw_data onto persisted work items.
 */
class WorkItemNurseAssignmentService
{
    /**
     * @param  array<int, array{code: string, quantity: int, nurse_id?: int|null}>  $treatmentLines
     */
    public function storeAssignmentsOnRow(DailyWorkRow $dailyWorkRow, array $treatmentLines): void
    {
        $assignments = [];

        foreach ($treatmentLines as $line) {
            if (! isset($line['nurse_id']) || $line['nurse_id'] === null || $line['nurse_id'] === '') {
                continue;
            }

            $assignments[strtoupper(trim((string) $line['code']))] = (int) $line['nurse_id'];
        }

        $rawData = is_array($dailyWorkRow->raw_data_json) ? $dailyWorkRow->raw_data_json : [];
        $rawData['nurse_assignments'] = $assignments;
        $dailyWorkRow->raw_data_json = $rawData;
        $dailyWorkRow->save();
    }

    public function applyFromWorkRow(DailyWorkRow $dailyWorkRow): void
    {
        $rawData = is_array($dailyWorkRow->raw_data_json) ? $dailyWorkRow->raw_data_json : [];
        $assignments = $rawData['nurse_assignments'] ?? [];

        if ($assignments === []) {
            return;
        }

        $workItems = AccountingScopedQuery::workItems((int) $dailyWorkRow->clinic_id, $dailyWorkRow->id)
            ->with('treatment')
            ->get();

        foreach ($workItems as $workItem) {
            $code = strtoupper((string) $workItem->treatment->code);
            $nurseId = $assignments[$code] ?? null;

            if ($nurseId === null) {
                $workItem->nurse_id = null;
            } else {
                $workItem->nurse_id = (int) $nurseId;
            }

            $workItem->save();
        }
    }

    /**
     * @return array<string, int> treatment code => nurse_id
     */
    public function assignmentsFromWorkRow(DailyWorkRow $dailyWorkRow): array
    {
        $rawData = is_array($dailyWorkRow->raw_data_json) ? $dailyWorkRow->raw_data_json : [];

        return is_array($rawData['nurse_assignments'] ?? null)
            ? $rawData['nurse_assignments']
            : [];
    }

    /**
     * @return array<int, array{code: string, quantity: int, nurse_id: int|null}>
     */
    public function treatmentLinesFromWorkRow(DailyWorkRow $dailyWorkRow): array
    {
        $assignments = $this->assignmentsFromWorkRow($dailyWorkRow);

        return AccountingScopedQuery::workItems((int) $dailyWorkRow->clinic_id, $dailyWorkRow->id)
            ->with('treatment')
            ->get()
            ->map(fn (WorkItem $workItem) => [
                'code' => $workItem->treatment->code,
                'quantity' => (int) $workItem->quantity,
                'nurse_id' => $workItem->nurse_id ?? ($assignments[strtoupper($workItem->treatment->code)] ?? null),
            ])
            ->values()
            ->all();
    }
}
