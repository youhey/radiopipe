<?php

namespace Tests\Unit\Scenarios;

use App\Scenarios\ScenarioTopicSelectionStatus;
use App\Scenarios\ScenarioTopicSelector;
use App\Topics\Editorial\TopicDuplicateAssessment;
use App\Topics\Editorial\TopicEditorialEvaluation;
use App\Topics\Editorial\TopicEditorialFlags;
use App\Topics\Editorial\TopicEditorialScores;
use App\Topics\Editorial\TopicEditorialStatus;
use App\Topics\Editorial\TopicLocalizedText;
use App\Topics\Editorial\TopicScenarioNotes;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class ScenarioTopicSelectorTest extends TestCase
{
    public function testSelectorUsesOnlyPendingEditorialEvaluationsAndSortsByScore(): void
    {
        $selector = new ScenarioTopicSelector();

        $selections = $selector->select([
            $this->evaluation('topic-low', 60),
            $this->evaluation('topic-skipped', 100, TopicEditorialStatus::SkippedLowValue),
            $this->evaluation('topic-high', 90),
            $this->evaluation('topic-mid', 75),
        ], maxTopics: 2);

        self::assertCount(3, $selections);
        self::assertSame('topic-high', $selections[0]->topicId);
        self::assertSame(ScenarioTopicSelectionStatus::UsedInScenario, $selections[0]->status);
        self::assertSame(1, $selections[0]->rank);
        self::assertSame('topic-mid', $selections[1]->topicId);
        self::assertSame(ScenarioTopicSelectionStatus::UsedInScenario, $selections[1]->status);
        self::assertSame(2, $selections[1]->rank);
        self::assertSame('topic-low', $selections[2]->topicId);
        self::assertSame(ScenarioTopicSelectionStatus::SelectedNotUsed, $selections[2]->status);
        self::assertNull($selections[2]->rank);
    }

    public function testSelectorUsesDeterministicFallbackTopicId(): void
    {
        $selector = new ScenarioTopicSelector();

        $selections = $selector->select([$this->evaluation(null, 80)], maxTopics: 1);

        self::assertSame('topic:1', $selections[0]->topicId);
    }

    private function evaluation(?string $topicId, int $score, TopicEditorialStatus $status = TopicEditorialStatus::Pending): TopicEditorialEvaluation
    {
        return new TopicEditorialEvaluation(
            status: $status,
            editorialScore: $score,
            localized: new TopicLocalizedText(
                title: 'テストトピック',
                summary: 'テスト用の要約です。',
                whyItMatters: 'テスト用の背景です。',
            ),
            scores: new TopicEditorialScores(80, 80, 80, 80, 80, 80),
            flags: new TopicEditorialFlags(false, false, false),
            duplicate: new TopicDuplicateAssessment(null, [], null, null, null),
            scenarioNotes: new TopicScenarioNotes(null, null, null, []),
            metadata: array_filter([
                'topic_id' => $topicId,
            ], static fn (?string $value): bool => $value !== null),
        );
    }
}
