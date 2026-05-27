<?php

namespace Tests\Unit\Scenarios;

use App\Scenarios\FakeScenarioGenerator;
use App\Scenarios\ScenarioGenerationInput;
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
class FakeScenarioGeneratorTest extends TestCase
{
    public function testFakeGeneratorCreatesScenarioWithOpeningTopicAndClosingSections(): void
    {
        $generator = new FakeScenarioGenerator(new ScenarioTopicSelector(), maxTopics: 2);

        $result = $generator->generate(new ScenarioGenerationInput(
            characterKey: 'neko_nyan_balanced_radio',
            targetDurationSeconds: 900,
            title: '今日のギークニュース',
            language: 'ja',
            editorialEvaluations: [
                $this->evaluation('topic-low', '低い話題', 60),
                $this->evaluation('topic-high', '高い話題', 90),
                $this->evaluation('topic-skipped', '除外話題', 100, TopicEditorialStatus::SkippedSensitive),
            ],
        ));

        self::assertSame('fake', $result->metadata['generator']);
        self::assertSame(2, $result->metadata['selected_topic_count']);
        self::assertSame('今日のギークニュース', $result->scenario->title);
        self::assertSame('neko_nyan_balanced_radio', $result->scenario->characterKey);
        self::assertNotSame('', $result->scenario->scriptText);
        self::assertCount(4, $result->scenario->sections);
        self::assertSame('opening', $result->scenario->sections[0]->type);
        self::assertSame('topic', $result->scenario->sections[1]->type);
        self::assertSame(['topic-high'], $result->scenario->sections[1]->topicIds);
        self::assertSame('topic', $result->scenario->sections[2]->type);
        self::assertSame(['topic-low'], $result->scenario->sections[2]->topicIds);
        self::assertSame('closing', $result->scenario->sections[3]->type);
        self::assertStringContainsString('高い話題', $result->scenario->scriptText);
        self::assertStringNotContainsString('除外話題', $result->scenario->scriptText);
        self::assertGreaterThanOrEqual(30, $result->scenario->estimatedDurationSeconds);
    }

    public function testFakeGeneratorReturnsTopicSelections(): void
    {
        $generator = new FakeScenarioGenerator(new ScenarioTopicSelector(), maxTopics: 1);

        $result = $generator->generate(new ScenarioGenerationInput(
            characterKey: null,
            targetDurationSeconds: 300,
            title: null,
            language: 'ja',
            editorialEvaluations: [
                $this->evaluation('topic-a', 'A', 80),
                $this->evaluation('topic-b', 'B', 70),
            ],
        ));

        self::assertCount(2, $result->topicSelections);
        self::assertSame(ScenarioTopicSelectionStatus::UsedInScenario, $result->topicSelections[0]->status);
        self::assertSame(ScenarioTopicSelectionStatus::SelectedNotUsed, $result->topicSelections[1]->status);
        self::assertSame('今日のギークニュース', $result->scenario->title);
    }

    private function evaluation(
        string $topicId,
        string $title,
        int $score,
        TopicEditorialStatus $status = TopicEditorialStatus::Pending,
    ): TopicEditorialEvaluation {
        return new TopicEditorialEvaluation(
            status: $status,
            editorialScore: $score,
            localized: new TopicLocalizedText(
                title: $title,
                summary: "{$title} の要約です。",
                whyItMatters: "{$title} の背景です。",
            ),
            scores: new TopicEditorialScores(80, 80, 80, 80, 80, 80),
            flags: new TopicEditorialFlags(false, false, false),
            duplicate: new TopicDuplicateAssessment(null, [], null, null, null),
            scenarioNotes: new TopicScenarioNotes(null, null, null, []),
            metadata: [
                'topic_id' => $topicId,
            ],
        );
    }
}
