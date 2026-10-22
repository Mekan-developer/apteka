<?php

namespace App\Enums;

/**
 * Result of parsing one sheet (one pharmacy) of an uploaded price file.
 */
enum PriceUploadSheetStatus: string
{
    case Parsed = 'parsed';
    case NeedsReview = 'needs_review';
    case Skipped = 'skipped';
    case Failed = 'failed';
}
