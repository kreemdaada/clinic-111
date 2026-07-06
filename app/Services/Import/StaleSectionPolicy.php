<?php

namespace App\Services\Import;

use Carbon\Carbon;

/**
 * Decides whether a doctor section should be skipped as stale carry-over data.
 */
final class StaleSectionPolicy
{
    private const CURRENT_FILE_NUMBER_THRESHOLD = 6200;

    private const STALE_TOTAL_COST_THRESHOLD = 10000;

    /**
     * @param  string|null  $sectionAnchorDate  First patient date in the section (Y-m-d).
     * @param  int  $sectionMaxFileNumber  Highest NF file number seen in the section.
     * @param  Carbon|null  $reportMonth  Target import month.
     * @param  array<string, mixed>  $rowData  Subtotal row with total_cost.
     * @param  int  $sheetDay  Day-of-month from the sheet tab name.
     */
    public function shouldSkip(
        ?string $sectionAnchorDate,
        int $sectionMaxFileNumber,
        ?Carbon $reportMonth,
        array $rowData,
        int $sheetDay,
    ): bool {
        if ($reportMonth === null || $sectionAnchorDate === null) {
            return false;
        }

        try {
            $anchorDate = Carbon::parse($sectionAnchorDate);
        } catch (\Throwable) {
            return false;
        }

        if ($this->sectionAnchorMatchesSheetDay($anchorDate, $reportMonth, $sheetDay)) {
            return false;
        }

        if ($anchorDate->year >= $reportMonth->year) {
            return false;
        }

        if ($sectionMaxFileNumber >= self::CURRENT_FILE_NUMBER_THRESHOLD) {
            return false;
        }

        if ($sectionMaxFileNumber > 0) {
            return true;
        }

        $totalCost = (float) preg_replace('/[^\d.\-]/', '', (string) ($rowData['total_cost'] ?? 0));

        return $totalCost >= self::STALE_TOTAL_COST_THRESHOLD;
    }

    /**
     * Whether the anchor cell month/day align with the canonical sheet date for this report.
     *
     * Copied workbook templates often keep an old year in column A while the sheet tab
     * and report month define the intended work day.
     */
    private function sectionAnchorMatchesSheetDay(Carbon $anchorDate, Carbon $reportMonth, int $sheetDay): bool
    {
        if ($sheetDay < 1 || $sheetDay > (int) $reportMonth->daysInMonth) {
            return false;
        }

        return (int) $anchorDate->month === (int) $reportMonth->month
            && (int) $anchorDate->day === $sheetDay;
    }
}
