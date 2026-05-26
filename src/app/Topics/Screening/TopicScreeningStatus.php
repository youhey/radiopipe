<?php

namespace App\Topics\Screening;

/**
 * Stage 1 の Topic Screening Status
 */
enum TopicScreeningStatus: string
{
    case Passed = 'passed';
    case RejectedLowValue = 'rejected_low_value';
    case RejectedDuplicate = 'rejected_duplicate';
    case RejectedUncertain = 'rejected_uncertain';
    case RejectedSensitive = 'rejected_sensitive';
}
