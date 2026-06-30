<?php

namespace App\Enums;

use App\Models\DailyReport;

/**
 * Processing state of a {@see DailyReport} import pipeline.
 *
 * Advances: uploaded → parsed → calculated|needs_review → approved.
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

    /** Import completed but parser/lab warnings require human review. */
    case NeedsReview = 'needs_review';

    /** Report approved — read-only until admin unlocks with reason. */
    case Approved = 'approved';

    /** Report explicitly locked — same read-only rules as approved. */
    case Locked = 'locked';

    /** Import or calculation failed; no partial data committed. */
    case Failed = 'failed';
}
