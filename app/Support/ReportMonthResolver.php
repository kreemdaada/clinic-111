<?php

namespace App\Support;

use Carbon\Carbon;
use RuntimeException;

/**
 * Derives the accounting month and per-row work dates from file names and sheet metadata.
 *
 * Never uses upload date — month must appear in the Excel file name.
 */
class ReportMonthResolver
{
    /** @var array<string, int> Lowercase month name / abbreviation → month number. */
    private const MONTH_NAMES = [
        'january' => 1,
        'jan' => 1,
        'february' => 2,
        'feb' => 2,
        'march' => 3,
        'mar' => 3,
        'april' => 4,
        'apr' => 4,
        'may' => 5,
        'june' => 6,
        'jun' => 6,
        'july' => 7,
        'jul' => 7,
        'august' => 8,
        'aug' => 8,
        'september' => 9,
        'sep' => 9,
        'sept' => 9,
        'october' => 10,
        'oct' => 10,
        'november' => 11,
        'nov' => 11,
        'december' => 12,
        'dec' => 12,
    ];

    /**
     * Parse report month from file name or throw if it cannot be detected.
     *
     * @param  string|null  $fileName  Uploaded Excel file name.
     * @return Carbon First day of the resolved month at 00:00:00.
     *
     * @throws RuntimeException When no month/year pattern matches the file name.
     */
    public static function requireFromFilename(?string $fileName): Carbon
    {
        $month = self::parseFromFilename($fileName);

        if ($month === null) {
            throw new RuntimeException(
                'Could not detect the report month from the file name. '
                .'Use a name like "daily report April 2026.xlsm" or "daily report 2026-04.xlsm".'
            );
        }

        return $month->copy()->startOfMonth();
    }

    /**
     * Try to parse report month from file name without throwing.
     *
     * Supports: "daily report January 2026", "2026-04", "04-2026", etc.
     *
     * @param  string|null  $fileName  Uploaded Excel file name.
     * @return Carbon|null First day of month, or null when pattern not found.
     */
    public static function parseFromFilename(?string $fileName): ?Carbon
    {
        if ($fileName === null || trim($fileName) === '') {
            return null;
        }

        $normalized = strtolower($fileName);

        if (preg_match('/\b(january|jan|february|feb|march|mar|april|apr|may|june|jun|july|jul|august|aug|september|sept|sep|october|oct|november|nov|december|dec)\b[^0-9]*(\d{4})/', $normalized, $matches)) {
            return self::monthFromParts((int) $matches[2], self::MONTH_NAMES[$matches[1]] ?? null);
        }

        if (preg_match('/\b(\d{4})[-_.](\d{1,2})\b/', $normalized, $matches)) {
            return self::monthFromParts((int) $matches[1], (int) $matches[2]);
        }

        if (preg_match('/\b(\d{1,2})[-_.](\d{4})\b/', $normalized, $matches)) {
            return self::monthFromParts((int) $matches[2], (int) $matches[1]);
        }

        return null;
    }

    /**
     * Resolve the calendar date for one imported row within the report month.
     *
     * Prefers sheet day number (1–31) when valid; otherwise stored work_date if in same month;
     * falls back to first day of month.
     *
     * @param  Carbon  $monthStart  First day of the report month.
     * @param  mixed  $storedWorkDate  Date from Excel or parser (string|null).
     * @param  mixed  $sheetDay  Day-of-month from sheet name or row (int|string|null).
     * @return string ISO date string (`YYYY-MM-DD`).
     */
    public static function resolveWorkDateForRow(Carbon $monthStart, mixed $storedWorkDate, mixed $sheetDay): string
    {
        if ($sheetDay !== null && is_numeric($sheetDay)) {
            $day = (int) $sheetDay;

            if ($day >= 1 && $day <= (int) $monthStart->daysInMonth) {
                return $monthStart->copy()->day($day)->toDateString();
            }
        }

        if ($storedWorkDate !== null && $storedWorkDate !== '') {
            $parsed = Carbon::parse((string) $storedWorkDate);

            if ($parsed->year === (int) $monthStart->year && $parsed->month === (int) $monthStart->month) {
                return $parsed->toDateString();
            }
        }

        return $monthStart->toDateString();
    }

    /**
     * Build a Carbon month start from year and month number with validation.
     *
     * @param  int  $year  Four-digit year (2000–2100).
     * @param  int|null  $monthNumber  Month 1–12.
     * @return Carbon|null First day of month, or null when inputs are invalid.
     */
    private static function monthFromParts(int $year, ?int $monthNumber): ?Carbon
    {
        if ($monthNumber === null || $monthNumber < 1 || $monthNumber > 12) {
            return null;
        }

        if ($year < 2000 || $year > 2100) {
            return null;
        }

        return Carbon::createFromDate($year, $monthNumber, 1)->startOfMonth();
    }
}
