<?php

namespace App\Topics\Editorial;

use App\Topics\TopicDraft;

/**
 * TopicDraft を Phase 6 の Editorial Evaluation に変換する Analyzer
 */
interface TopicEditorialAnalyzer
{
    /**
     * TopicDraft を Editorial Evaluation として解析
     *
     * @param TopicDraft $topicDraft
     *
     * @return TopicEditorialEvaluation
     */
    public function analyze(TopicDraft $topicDraft): TopicEditorialEvaluation;
}
