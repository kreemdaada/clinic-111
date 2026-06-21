<?php

namespace App\Enums;

/**
 * Processing state of a {@see \App\Models\DailyReport} import pipeline.
 *
 * Advances: uploaded → parsed → calculated → approved.
 * On failure the report is marked `failed` and the DB transaction is rolled back.
 */
enum ReportStatus: string
{
    /** File received; import transaction started. */
    case Uploaded = 'uploaded';

    /** Rows and payments created; treatments parsed into work items. */
    case Parsed = 'parsed';

    /** Lab jobs calculated for all lab-cost work items. */
    case Calculated = 'calculated';

    /** Report locked — read-only; re-import for same date is rejected. */
    case Approved = 'approved';

    /** Import or calculation failed; no partial data committed. */
    case Failed = 'failed';
}
