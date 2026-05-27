<?php

namespace Tests\Feature;

use App\Models\TopicScreeningKeywordRule;
use App\Topics\Screening\TopicScreeningEvaluation;
use App\Topics\Screening\TopicScreeningEvaluator;
use App\Topics\Screening\TopicScreeningKeywordRuleProvider;
use App\Topics\Screening\TopicScreeningStatus;
use App\Topics\TopicDraft;
use Carbon\CarbonImmutable;
use Database\Seeders\TopicScreeningKeywordRuleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * @internal
 */
class TopicScreeningEvaluatorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(TopicScreeningKeywordRuleSeeder::class);
    }

    public function testHighQualityFreshTopicPassesScreening(): void
    {
        $evaluation = $this->evaluate($this->draft());

        self::assertSame(TopicScreeningStatus::Passed, $evaluation->screeningStatus);
        self::assertSame(90, $evaluation->screeningScore);
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
        self::assertSame(8, $selected->screeningScore - $skipped->screeningScore);
    }

    public function testSelectionScoreDoesNotDominateScreeningScore(): void
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
        self::assertSame(4, $large->screeningScore - $zero->screeningScore);
    }

    public function testDuplicateUrlBecomesRejectedDuplicate(): void
    {
        $evaluation = $this->evaluate($this->draft(), ['https://example.test/article']);

        self::assertSame(TopicScreeningStatus::RejectedDuplicate, $evaluation->screeningStatus);
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

        self::assertSame(TopicScreeningStatus::RejectedUncertain, $evaluation->screeningStatus);
        self::assertTrue($evaluation->flags['is_uncertain']);
        self::assertSame(44, $evaluation->signals['upstream_confidence_score']);
        self::assertContains('digestpipe confidence is low', $evaluation->reasons);
    }

    public function testFreeFormContentTypesUseNeutralUnknownScoreByDefault(): void
    {
        foreach (['news/article', 'news_report', 'blog post', 'how-to', 'opinion/essay'] as $contentType) {
            $evaluation = $this->evaluate($this->draft(contentType: $contentType));

            self::assertSame(50, $evaluation->signals['content_type_score']);
            self::assertNotContains('content type is useful', $evaluation->reasons);
            self::assertNotContains('content type is weak', $evaluation->reasons);
        }
    }

    public function testExplicitlyConfiguredContentTypeScoresStillWork(): void
    {
        config([
            'radiopipe.topic_screening.content_type_scores' => [
                'technical_article' => 85,
                'landing_page' => 25,
                'unknown' => 50,
            ],
        ]);

        $strong = $this->evaluate($this->draft(contentType: 'technical_article'));
        $weak = $this->evaluate($this->draft(contentType: 'landing_page'));
        $freeForm = $this->evaluate($this->draft(contentType: 'news/article'));

        self::assertSame(85, $strong->signals['content_type_score']);
        self::assertSame(25, $weak->signals['content_type_score']);
        self::assertSame(50, $freeForm->signals['content_type_score']);
        self::assertLessThan($strong->screeningScore, $weak->screeningScore);
        self::assertContains('content type is useful', $strong->reasons);
        self::assertContains('content type is weak', $weak->reasons);
    }

    public function testLimitationKeywordsApplyPenaltyAndUncertainty(): void
    {
        $evaluation = $this->evaluate($this->draft(limitations: 'This is title only and has insufficient context.'));

        self::assertSame(30, $evaluation->signals['limitation_penalty']);
        self::assertTrue($evaluation->flags['is_uncertain']);
        self::assertSame(TopicScreeningStatus::RejectedUncertain, $evaluation->screeningStatus);
        self::assertContains('limitations mention weak source quality', $evaluation->reasons);
    }

    public function testActiveLimitationRuleAppliesMaximumPenalty(): void
    {
        TopicScreeningKeywordRule::factory()->create([
            'rule_type' => TopicScreeningKeywordRule::TYPE_LIMITATION,
            'keyword' => 'limited extraction',
            'target_fields' => [TopicScreeningKeywordRule::FIELD_LIMITATIONS],
            'penalty' => 12,
            'action' => TopicScreeningKeywordRule::ACTION_FLAG,
            'sort_order' => 1,
        ]);
        TopicScreeningKeywordRule::factory()->create([
            'rule_type' => TopicScreeningKeywordRule::TYPE_LIMITATION,
            'keyword' => 'extraction notes',
            'target_fields' => [TopicScreeningKeywordRule::FIELD_LIMITATIONS],
            'penalty' => 40,
            'action' => TopicScreeningKeywordRule::ACTION_FLAG,
            'sort_order' => 2,
        ]);

        $evaluation = $this->evaluate($this->draft(limitations: 'This has limited extraction notes.'));

        self::assertSame(40, $evaluation->signals['limitation_penalty']);
    }

    public function testClearlySensitiveTopicSetsSensitivityFlag(): void
    {
        $evaluation = $this->evaluate($this->draft(
            title: 'Security breach exposes customer personal data',
            tags: ['security breach'],
        ));

        self::assertSame(TopicScreeningStatus::RejectedSensitive, $evaluation->screeningStatus);
        self::assertTrue($evaluation->flags['is_sensitive']);
        self::assertContains('topic contains sensitive keyword', $evaluation->reasons);
    }

    public function testInactiveRulesAreIgnored(): void
    {
        TopicScreeningKeywordRule::query()->where('keyword', 'security breach')->update(['is_active' => false]);
        TopicScreeningKeywordRule::query()->where('keyword', 'personal data')->update(['is_active' => false]);

        $evaluation = $this->evaluate($this->draft(
            title: 'Security breach exposes customer personal data',
            tags: ['security breach'],
        ));

        self::assertFalse($evaluation->flags['is_sensitive']);
        self::assertSame(TopicScreeningStatus::Passed, $evaluation->screeningStatus);
    }

    public function testEmptyRuleTableSkipsKeywordMatchingAndLogsWarning(): void
    {
        TopicScreeningKeywordRule::query()->delete();
        config([
            'radiopipe.topic_screening.limitation_keywords' => ['title only'],
            'radiopipe.topic_screening.sensitive_keywords' => ['security breach'],
        ]);
        Log::shouldReceive('warning')
            ->once()
            ->with('No active topic screening keyword rules found. Keyword matching will be skipped.');

        $evaluation = $this->evaluate($this->draft(
            title: 'Security breach exposes customer personal data',
            limitations: 'This is title only and has insufficient context.',
            tags: ['security breach'],
        ));

        self::assertSame(0, $evaluation->signals['limitation_penalty']);
        self::assertFalse($evaluation->flags['is_sensitive']);
        self::assertFalse($evaluation->flags['is_uncertain']);
        self::assertSame(TopicScreeningStatus::Passed, $evaluation->screeningStatus);
    }

    public function testSensitiveFlagActionDoesNotRejectByItself(): void
    {
        TopicScreeningKeywordRule::query()->delete();
        TopicScreeningKeywordRule::factory()->create([
            'rule_type' => TopicScreeningKeywordRule::TYPE_SENSITIVE,
            'keyword' => 'security breach',
            'target_fields' => [TopicScreeningKeywordRule::FIELD_TITLE],
            'penalty' => null,
            'action' => TopicScreeningKeywordRule::ACTION_FLAG,
        ]);

        $evaluation = $this->evaluate($this->draft(title: 'Security breach analysis'));

        self::assertTrue($evaluation->flags['is_sensitive']);
        self::assertSame(TopicScreeningStatus::Passed, $evaluation->screeningStatus);
    }

    public function testLowTotalScreeningScoreBecomesRejectedLowValue(): void
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

        self::assertSame(TopicScreeningStatus::RejectedLowValue, $evaluation->screeningStatus);
        self::assertLessThan(45, $evaluation->screeningScore);
        self::assertContains('screening score is below threshold', $evaluation->reasons);
    }

    public function testScreeningScoreIsClampedToZeroAndOneHundred(): void
    {
        config([
            'radiopipe.topic_screening.weights.freshness' => 1.0,
            'radiopipe.topic_screening.weights.importance' => 1.0,
            'radiopipe.topic_screening.weights.confidence' => 1.0,
            'radiopipe.topic_screening.weights.content_type' => 1.0,
        ]);

        self::assertSame(100, $this->evaluate($this->draft())->screeningScore);

        config([
            'radiopipe.topic_screening.weights.freshness' => 0.25,
            'radiopipe.topic_screening.weights.importance' => 0.35,
            'radiopipe.topic_screening.weights.confidence' => 0.25,
            'radiopipe.topic_screening.weights.content_type' => 0.15,
        ]);

        TopicScreeningKeywordRule::query()
            ->where('rule_type', TopicScreeningKeywordRule::TYPE_LIMITATION)
            ->where('keyword', 'headline only')
            ->update(['penalty' => 250]);

        self::assertSame(0, $this->evaluate($this->draft(limitations: 'headline only'))->screeningScore);
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
        self::assertSame(50, $evaluation->signals['content_type_score']);
        self::assertFalse($evaluation->flags['is_duplicate']);
        self::assertFalse($evaluation->flags['is_sensitive']);
    }

    public function testEvaluationSerializesToArray(): void
    {
        $evaluation = $this->evaluate($this->draft());

        self::assertSame([
            'screening_status' => 'passed',
            'screening_score' => 90,
            'signals' => [
                'is_duplicate_url' => false,
                'freshness_score' => 100,
                'upstream_importance_score' => 80,
                'upstream_confidence_score' => 96,
                'content_type_score' => 50,
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
                'upstream selection status is selected',
            ],
        ], $evaluation->toArray());
    }

    /**
     * @param list<string> $seenUrls
     */
    private function evaluate(TopicDraft $draft, array $seenUrls = []): TopicScreeningEvaluation
    {
        return (new TopicScreeningEvaluator(new TopicScreeningKeywordRuleProvider()))->evaluate(
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
