<?php

namespace App\Topics\Editorial;

/**
 * Topic Editorial Evaluation の Phase Status
 */
enum TopicEditorialStatus: string
{
    case Pending = 'pending';
    case SkippedLowValue = 'skipped_low_value';
    case SkippedDuplicate = 'skipped_duplicate';
    case SkippedUncertain = 'skipped_uncertain';
    case SkippedSensitive = 'skipped_sensitive';
}
