<?php

namespace App\Scenarios;

use App\Topics\Editorial\TopicEditorialEvaluation;
use App\Topics\Editorial\TopicEditorialStatus;

/**
 * Editorial Evaluation から Scenario に使う topic を選択する最小 selector。
 */
class ScenarioTopicSelector
{
    /**
     * pending topic を score 順に選択する。
     *
     * @param list<TopicEditorialEvaluation> $editorialEvaluations
     *
     * @return list<ScenarioTopicSelection>
     */
    public function select(array $editorialEvaluations, int $maxTopics): array
    {
        $pending = [];

        foreach ($editorialEvaluations as $index => $evaluation) {
            if ($evaluation->status !== TopicEditorialStatus::Pending) {
                continue;
            }

            $pending[] = [
                'index' => $index,
                'evaluation' => $evaluation,
                'topic_id' => $this->topicId($evaluation, $index),
            ];
        }

        usort(
            $pending,
            static fn (array $left, array $right): int => [$right['evaluation']->editorialScore, -$right['index']]
                <=> [$left['evaluation']->editorialScore, -$left['index']],
        );

        $selections = [];
        $usedCount = 0;
        $maxTopics = max(0, $maxTopics);

        foreach ($pending as $item) {
            $used = $usedCount < $maxTopics;

            $selections[] = new ScenarioTopicSelection(
                topicId: $item['topic_id'],
                status: $used
                    ? ScenarioTopicSelectionStatus::UsedInScenario
                    : ScenarioTopicSelectionStatus::SelectedNotUsed,
                rank: $used ? $usedCount + 1 : null,
                reason: $used
                    ? 'selected by editorial score'
                    : 'not used because max topic count was reached',
                metadata: [
                    'editorial_score' => $item['evaluation']->editorialScore,
                    'source_index' => $item['index'],
                ],
            );

            if ($used) {
                ++$usedCount;
            }
        }

        return $selections;
    }

    /**
     * Evaluation metadata から topic id を取得する。
     */
    public function topicId(TopicEditorialEvaluation $evaluation, int $index): string
    {
        $topicId = $evaluation->metadata['topic_id'] ?? null;

        if (is_string($topicId) && $topicId !== '') {
            return $topicId;
        }

        return sprintf('topic:%d', $index + 1);
    }
}
