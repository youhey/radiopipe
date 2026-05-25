<?php

namespace App\Topics\Editorial;

use App\Topics\TopicDraft;

/**
 * TopicDraft を Phase 6 の editorial evaluation に変換する analyzer です。
 */
interface TopicEditorialAnalyzer
{
    /**
     * TopicDraft を editorial evaluation として解析します。
     */
    public function analyze(TopicDraft $topicDraft): TopicEditorialEvaluation;
}
