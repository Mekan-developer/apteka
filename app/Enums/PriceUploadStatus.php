<?php

namespace App\Enums;

/**
 * Lifecycle of an uploaded price file. Only the single Active upload takes part in search.
 */
enum PriceUploadStatus: string
{
    case Uploaded = 'uploaded';
    case Processing = 'processing';
    case NeedsReview = 'needs_review';
    case Active = 'active';
    case Archived = 'archived';
    case Failed = 'failed';
}
