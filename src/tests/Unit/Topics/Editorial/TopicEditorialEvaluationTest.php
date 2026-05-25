<?php

namespace Tests\Unit\Topics\Editorial;

use App\Topics\Editorial\TopicDuplicateAssessment;
use App\Topics\Editorial\TopicEditorialEvaluation;
use App\Topics\Editorial\TopicEditorialFlags;
use App\Topics\Editorial\TopicEditorialScores;
use App\Topics\Editorial\TopicEditorialStatus;
use App\Topics\Editorial\TopicLocalizedText;
use App\Topics\Editorial\TopicScenarioNotes;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class TopicEditorialEvaluationTest extends TestCase
{
    public function testEvaluationExportsExpectedNestedArrayShape(): void
    {
        $evaluation = $this->evaluation();

        self::assertSame([
            'status' => 'pending',
            'editorial_score' => 86,
            'localized' => [
                'title' => 'AIチップの部品コストでHBMの比率が上昇',
                'summary' => 'AIチップの部品コストに占めるHBMの割合が伸びています。',
                'why_it_matters' => 'AIインフラの供給網と設備投資に影響する可能性があります。',
            ],
            'scores' => [
                'preference' => 90,
                'general_importance' => 85,
                'freshness' => 80,
                'certainty' => 88,
                'scenario_fitness' => 82,
                'flow_flexibility' => 70,
            ],
            'flags' => [
                'is_duplicate_candidate' => false,
                'is_uncertain' => false,
                'is_sensitive' => false,
            ],
            'duplicate' => [
                'canonical_key' => 'ai-chip-hbm-component-costs',
                'similar_topic_ids' => ['topic-previous'],
                'duplicate_of' => null,
                'confidence' => 72,
                'reason' => 'similar title and entities',
            ],
            'scenario_notes' => [
                'suggested_role' => 'top_story',
                'tone' => 'serious_but_accessible',
                'transition_hint' => 'AIインフラのコスト構造という流れで紹介できる',
                'avoid' => ['avoid overclaiming'],
            ],
            'reasons' => [
                'strong preference fit',
                'usable for scenario opening',
            ],
            'metadata' => [
                'analyzer' => 'fixture',
            ],
        ], $evaluation->toArray());
    }

    public function testStatusExposesExpectedValues(): void
    {
        self::assertSame('pending', TopicEditorialStatus::Pending->value);
        self::assertSame('skipped_low_value', TopicEditorialStatus::SkippedLowValue->value);
        self::assertSame('skipped_duplicate', TopicEditorialStatus::SkippedDuplicate->value);
        self::assertSame('skipped_uncertain', TopicEditorialStatus::SkippedUncertain->value);
        self::assertSame('skipped_sensitive', TopicEditorialStatus::SkippedSensitive->value);
    }

    public function testScoreValidationRejectsValuesBelowZero(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new TopicEditorialScores(
            preference: -1,
            generalImportance: 85,
            freshness: 80,
            certainty: 88,
            scenarioFitness: 82,
            flowFlexibility: 70,
        );
    }

    public function testScoreValidationRejectsValuesAboveOneHundred(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new TopicEditorialEvaluation(
            status: TopicEditorialStatus::Pending,
            editorialScore: 101,
            localized: new TopicLocalizedText('', '', ''),
            scores: new TopicEditorialScores(0, 0, 0, 0, 0, 0),
            flags: new TopicEditorialFlags(false, false, false),
            duplicate: new TopicDuplicateAssessment(null, [], null, null, null),
            scenarioNotes: new TopicScenarioNotes(null, null, null, []),
        );
    }

    public function testOptionalDuplicateFieldsCanBeNull(): void
    {
        $duplicate = new TopicDuplicateAssessment(
            canonicalKey: null,
            similarTopicIds: [],
            duplicateOf: null,
            confidence: null,
            reason: null,
        );

        self::assertSame([
            'canonical_key' => null,
            'similar_topic_ids' => [],
            'duplicate_of' => null,
            'confidence' => null,
            'reason' => null,
        ], $duplicate->toArray());
    }

    public function testDuplicateConfidenceValidationRejectsInvalidValues(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new TopicDuplicateAssessment(
            canonicalKey: 'duplicate-key',
            similarTopicIds: [],
            duplicateOf: null,
            confidence: 101,
            reason: null,
        );
    }

    public function testScenarioNotesAcceptsUnknownSuggestedRole(): void
    {
        $notes = new TopicScenarioNotes(
            suggestedRole: 'unexpected_role',
            tone: null,
            transitionHint: null,
            avoid: [],
        );

        self::assertSame('unexpected_role', $notes->toArray()['suggested_role']);
    }

    private function evaluation(): TopicEditorialEvaluation
    {
        return new TopicEditorialEvaluation(
            status: TopicEditorialStatus::Pending,
            editorialScore: 86,
            localized: new TopicLocalizedText(
                title: 'AIチップの部品コストでHBMの比率が上昇',
                summary: 'AIチップの部品コストに占めるHBMの割合が伸びています。',
                whyItMatters: 'AIインフラの供給網と設備投資に影響する可能性があります。',
            ),
            scores: new TopicEditorialScores(
                preference: 90,
                generalImportance: 85,
                freshness: 80,
                certainty: 88,
                scenarioFitness: 82,
                flowFlexibility: 70,
            ),
            flags: new TopicEditorialFlags(
                isDuplicateCandidate: false,
                isUncertain: false,
                isSensitive: false,
            ),
            duplicate: new TopicDuplicateAssessment(
                canonicalKey: 'ai-chip-hbm-component-costs',
                similarTopicIds: ['topic-previous'],
                duplicateOf: null,
                confidence: 72,
                reason: 'similar title and entities',
            ),
            scenarioNotes: new TopicScenarioNotes(
                suggestedRole: 'top_story',
                tone: 'serious_but_accessible',
                transitionHint: 'AIインフラのコスト構造という流れで紹介できる',
                avoid: ['avoid overclaiming'],
            ),
            reasons: [
                'strong preference fit',
                'usable for scenario opening',
            ],
            metadata: [
                'analyzer' => 'fixture',
            ],
        );
    }
}
