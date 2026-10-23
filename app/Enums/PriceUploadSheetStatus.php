<?php

namespace App\Enums;

/**
 * Result of parsing one sheet (one pharmacy) of an uploaded price file.
 * Processing: an admin chose its columns and the sheet is being parsed again in the queue.
 */
enum PriceUploadSheetStatus: string
{
    case Processing = 'processing';
    case Parsed = 'parsed';
    case NeedsReview = 'needs_review';
    case Skipped = 'skipped';
    case Failed = 'failed';
}
