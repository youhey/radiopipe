<?php

namespace Tests\Feature;

use App\Topics\TopicDraft;
use App\Topics\TopicPreStatus;
use App\Topics\TopicRuleEvaluator;
use Carbon\CarbonImmutable;
use Tests\TestCase;

/**
 * @internal
 */
class TopicRuleEvaluatorTest extends TestCase
{
    public function testHighQualityFreshTopicBecomesPreselected(): void
    {
        $evaluation = $this->evaluate($this->draft());

        self::assertSame(TopicPreStatus::Preselected, $evaluation->preStatus);
        self::assertSame(95, $evaluation->ruleScore);
        self::assertFalse($evaluation->flags['is_duplicate']);
        self::assertFalse($evaluation->flags['is_uncertain']);
        self::assertFalse($evaluation->flags['is_sensitive']);
        self::assertContains('article is fresh', $evaluation->reasons);
        self::assertContains('digestpipe importance is high', $evaluation->reasons);
        self::assertContains('source confidence is high', $evaluation->reasons);
        self::assertContains('upstream selection status is selected', $evaluation->reasons);
    }

    public function testImportanceMapsToTrustedScores(): void
    {
        self::assertSame(100, $this->evaluate($this->draft(importance: 5))->signals['upstream_importance_score']);
        self::assertSame(80, $this->evaluate($this->draft(importance: 4))->signals['upstream_importance_score']);
        self::assertSame(60, $this->evaluate($this->draft(importance: 3))->signals['upstream_importance_score']);
        self::assertSame(30, $this->evaluate($this->draft(importance: 2))->signals['upstream_importance_score']);
        self::assertSame(10, $this->evaluate($this->draft(importance: 1))->signals['upstream_importance_score']);
        self::assertSame(40, $this->evaluate($this->draft(importance: null))->signals['upstream_importance_score']);
    }

    public function testConfidenceMapsToTrustedScores(): void
    {
        self::assertSame(96, $this->evaluate($this->draft(confidence: 0.96))->signals['upstream_confidence_score']);
        self::assertSame(45, $this->evaluate($this->draft(confidence: 0.45))->signals['upstream_confidence_score']);
        self::assertSame(40, $this->evaluate($this->draft(confidence: null))->signals['upstream_confidence_score']);
    }

    public function testSelectionStatusProducesOnlySmallBoundedBonusOrPenalty(): void
    {
        $selected = $this->evaluate($this->draft(upstreamSelection: [
            'status' => 'selected',
            'score' => 50,
        ]));
        $skipped = $this->evaluate($this->draft(upstreamSelection: [
            'status' => 'skipped',
            'score' => 50,
        ]));
        $missing = $this->evaluate($this->draft(upstreamSelection: []));

        self::assertSame(5, $selected->signals['selection_bonus']);
        self::assertSame(-3, $skipped->signals['selection_bonus']);
        self::assertSame(0, $missing->signals['selection_bonus']);
        self::assertSame(8, $selected->ruleScore - $skipped->ruleScore);
    }

    public function testSelectionScoreDoesNotDominateRuleScore(): void
    {
        $zero = $this->evaluate($this->draft(upstreamSelection: [
            'status' => 'needs_content',
            'score' => 0,
        ]));
        $large = $this->evaluate($this->draft(upstreamSelection: [
            'status' => 'needs_content',
            'score' => 999,
        ]));

        self::assertSame(-2, $zero->signals['selection_bonus']);
        self::assertSame(2, $large->signals['selection_bonus']);
        self::assertSame(4, $large->ruleScore - $zero->ruleScore);
    }

    public function testDuplicateUrlBecomesPreSkippedDuplicate(): void
    {
        $evaluation = $this->evaluate($this->draft(), ['https://example.test/article']);

        self::assertSame(TopicPreStatus::PreSkippedDuplicate, $evaluation->preStatus);
        self::assertTrue($evaluation->flags['is_duplicate']);
        self::assertTrue($evaluation->signals['is_duplicate_url']);
        self::assertContains('duplicate URL', $evaluation->reasons);
    }

    public function testOldTopicReceivesLowFreshnessScore(): void
    {
        $evaluation = $this->evaluate($this->draft(
            publishedAt: CarbonImmutable::parse('2026-05-01T00:00:00Z'),
        ));

        self::assertSame(10, $evaluation->signals['freshness_score']);
        self::assertContains('article is stale', $evaluation->reasons);
    }

    public function testLowConfidenceTopicBecomesUncertain(): void
    {
        $evaluation = $this->evaluate($this->draft(confidence: 0.44));

        self::assertSame(TopicPreStatus::PreSkippedUncertain, $evaluation->preStatus);
        self::assertTrue($evaluation->flags['is_uncertain']);
        self::assertSame(44, $evaluation->signals['upstream_confidence_score']);
        self::assertContains('digestpipe confidence is low', $evaluation->reasons);
    }

    public function testWeakContentTypeLowersScore(): void
    {
        $strong = $this->evaluate($this->draft(contentType: 'technical_article'));
        $weak = $this->evaluate($this->draft(contentType: 'landing_page'));

        self::assertSame(85, $strong->signals['content_type_score']);
        self::assertSame(25, $weak->signals['content_type_score']);
        self::assertLessThan($strong->ruleScore, $weak->ruleScore);
        self::assertContains('content type is weak', $weak->reasons);
    }

    public function testLimitationKeywordsApplyPenaltyAndUncertainty(): void
    {
        $evaluation = $this->evaluate($this->draft(limitations: 'This is title only and has insufficient context.'));

        self::assertSame(30, $evaluation->signals['limitation_penalty']);
        self::assertTrue($evaluation->flags['is_uncertain']);
        self::assertSame(TopicPreStatus::PreSkippedUncertain, $evaluation->preStatus);
        self::assertContains('limitations mention weak source quality', $evaluation->reasons);
    }

    public function testClearlySensitiveTopicSetsSensitivityFlag(): void
    {
        $evaluation = $this->evaluate($this->draft(
            title: 'Security breach exposes customer personal data',
            tags: ['security breach'],
        ));

        self::assertSame(TopicPreStatus::PreSkippedSensitive, $evaluation->preStatus);
        self::assertTrue($evaluation->flags['is_sensitive']);
        self::assertContains('topic contains sensitive keyword', $evaluation->reasons);
    }

    public function testLowTotalRuleScoreBecomesPreSkippedLowValue(): void
    {
        $evaluation = $this->evaluate($this->draft(
            importance: 1,
            confidence: 0.46,
            contentType: 'privacy_policy',
            publishedAt: CarbonImmutable::parse('2026-05-23T00:00:00Z'),
            upstreamSelection: [
                'status' => 'skipped',
                'score' => 0,
            ],
        ));

        self::assertSame(TopicPreStatus::PreSkippedLowValue, $evaluation->preStatus);
        self::assertLessThan(45, $evaluation->ruleScore);
        self::assertContains('rule score is below threshold', $evaluation->reasons);
    }

    public function testRuleScoreIsClampedToZeroAndOneHundred(): void
    {
        config([
            'radiopipe.topic_rules.weights.freshness' => 1.0,
            'radiopipe.topic_rules.weights.importance' => 1.0,
            'radiopipe.topic_rules.weights.confidence' => 1.0,
            'radiopipe.topic_rules.weights.content_type' => 1.0,
        ]);

        self::assertSame(100, $this->evaluate($this->draft())->ruleScore);

        config([
            'radiopipe.topic_rules.penalties.limitation_keyword' => 250,
            'radiopipe.topic_rules.weights.freshness' => 0.25,
            'radiopipe.topic_rules.weights.importance' => 0.35,
            'radiopipe.topic_rules.weights.confidence' => 0.25,
            'radiopipe.topic_rules.weights.content_type' => 0.15,
        ]);

        self::assertSame(0, $this->evaluate($this->draft(limitations: 'headline only'))->ruleScore);
    }

    public function testMissingOptionalFieldsDoNotCrash(): void
    {
        $evaluation = $this->evaluate(new TopicDraft(
            id: 'upstream:minimal',
            sourceType: 'upstream',
            sourceName: null,
            title: null,
            originalTitle: null,
            url: null,
            discussionUrl: null,
            summarySeed: null,
            whyItMattersSeed: null,
            tags: [],
            entities: [],
            importance: null,
            confidence: null,
            contentType: null,
            limitations: null,
            publishedAt: null,
            fetchedAt: null,
            sourceRefs: [
                'provider' => 'digestpipe',
                'upstream_id' => 'minimal',
            ],
        ));

        self::assertSame(10, $evaluation->signals['freshness_score']);
        self::assertSame(40, $evaluation->signals['upstream_importance_score']);
        self::assertSame(40, $evaluation->signals['upstream_confidence_score']);
        self::assertSame(45, $evaluation->signals['content_type_score']);
        self::assertFalse($evaluation->flags['is_duplicate']);
        self::assertFalse($evaluation->flags['is_sensitive']);
    }

    public function testEvaluationSerializesToArray(): void
    {
        $evaluation = $this->evaluate($this->draft());

        self::assertSame([
            'pre_status' => 'preselected',
            'rule_score' => 95,
            'signals' => [
                'is_duplicate_url' => false,
                'freshness_score' => 100,
                'upstream_importance_score' => 80,
                'upstream_confidence_score' => 96,
                'content_type_score' => 85,
                'limitation_penalty' => 0,
                'selection_bonus' => 5,
            ],
            'flags' => [
                'is_duplicate' => false,
                'is_uncertain' => false,
                'is_sensitive' => false,
            ],
            'reasons' => [
                'article is fresh',
                'digestpipe importance is high',
                'source confidence is high',
                'content type is useful',
                'upstream selection status is selected',
            ],
        ], $evaluation->toArray());
    }

    /**
     * @param list<string> $seenUrls
     */
    private function evaluate(TopicDraft $draft, array $seenUrls = []): \App\Topics\TopicRuleEvaluation
    {
        return (new TopicRuleEvaluator())->evaluate(
            $draft,
            $seenUrls,
            CarbonImmutable::parse('2026-05-25T12:00:00Z'),
        );
    }

    /**
     * @param list<string>|null $tags
     * @param array{status?: string|null, score?: int|null}|null $upstreamSelection
     */
    private function draft(
        ?int $importance = 4,
        ?float $confidence = 0.96,
        ?string $contentType = 'data_analysis_article',
        ?CarbonImmutable $publishedAt = null,
        ?string $limitations = null,
        ?string $title = 'Useful technical article',
        ?array $tags = null,
        ?array $upstreamSelection = null,
    ): TopicDraft {
        return new TopicDraft(
            id: 'upstream:213',
            sourceType: 'upstream',
            sourceName: 'Hacker News',
            title: $title,
            originalTitle: $title,
            url: 'https://example.test/article',
            discussionUrl: 'https://news.ycombinator.com/item?id=213',
            summarySeed: 'A useful technical summary seed.',
            whyItMattersSeed: 'This affects engineering decisions.',
            tags: $tags ?? ['AI chips'],
            entities: ['Example Corp'],
            importance: $importance,
            confidence: $confidence,
            contentType: $contentType,
            limitations: $limitations,
            publishedAt: $publishedAt ?? CarbonImmutable::parse('2026-05-25T10:00:00Z'),
            fetchedAt: CarbonImmutable::parse('2026-05-25T11:00:00Z'),
            sourceRefs: [
                'provider' => 'digestpipe',
                'upstream_id' => 213,
            ],
            upstreamSelection: $upstreamSelection ?? [
                'status' => 'selected',
                'score' => 12,
            ],
        );
    }
}
