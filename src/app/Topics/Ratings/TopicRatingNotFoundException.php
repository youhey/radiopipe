<?php

namespace App\Topics\Ratings;

use RuntimeException;

/**
 * rating 対象 topic を upstream rating に解決できない場合の例外。
 */
class TopicRatingNotFoundException extends RuntimeException
{
}
