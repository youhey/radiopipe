<?php

namespace App\Topics;

/**
 * Stage 1 の内部 pre-evaluation status です。
 */
enum TopicPreStatus: string
{
    case Preselected = 'preselected';
    case PreSkippedLowValue = 'pre_skipped_low_value';
    case PreSkippedDuplicate = 'pre_skipped_duplicate';
    case PreSkippedUncertain = 'pre_skipped_uncertain';
    case PreSkippedSensitive = 'pre_skipped_sensitive';
}
