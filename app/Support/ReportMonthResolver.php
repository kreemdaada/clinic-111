<?php

namespace App\Support;

use Carbon\Carbon;
use RuntimeException;

class ReportMonthResolver
{
    /** @var array<string, int> */
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
