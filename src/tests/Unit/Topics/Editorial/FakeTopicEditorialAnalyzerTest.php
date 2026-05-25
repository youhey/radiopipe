<?php

namespace Tests\Unit\Topics\Editorial;

use App\Topics\Editorial\FakeTopicEditorialAnalyzer;
use App\Topics\Editorial\TopicEditorialAnalyzer;
use App\Topics\Editorial\TopicEditorialEvaluation;
use App\Topics\Editorial\TopicEditorialStatus;
use App\Topics\TopicDraft;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * @internal
 */
class FakeTopicEditorialAnalyzerTest extends TestCase
{
    public function testItReturnsTopicEditorialEvaluation(): void
    {
        $evaluation = (new FakeTopicEditorialAnalyzer())->analyze($this->draft());

        self::assertInstanceOf(TopicEditorialEvaluation::class, $evaluation);
    }

    public function testItProducesPendingForNormalHighQualityTopic(): void
    {
        $evaluation = (new FakeTopicEditorialAnalyzer())->analyze($this->draft());

        self::assertSame(TopicEditorialStatus::Pending, $evaluation->status);
        self::assertSame(83, $evaluation->editorialScore);
        self::assertSame('AI chip memory costs', $evaluation->localized->title);
        self::assertSame('main_story', $evaluation->scenarioNotes->suggestedRole);
        self::assertStringStartsWith('url-', (string) $evaluation->duplicate->canonicalKey);
    }

    public function testItProducesSkippedUncertainForLowConfidenceInput(): void
    {
        $evaluation = (new FakeTopicEditorialAnalyzer())->analyze($this->draft(confidence: 0.2));

        self::assertSame(TopicEditorialStatus::SkippedUncertain, $evaluation->status);
        self::assertTrue($evaluation->flags->isUncertain);
        self::assertContains('low_certainty', $evaluation->reasons);
        self::assertContains('avoid presenting as confirmed', $evaluation->scenarioNotes->avoid);
    }

    public function testItProducesSkippedSensitiveForSensitiveKeywordInput(): void
    {
        $evaluation = (new FakeTopicEditorialAnalyzer())->analyze($this->draft(
            title: 'Security breach exposes customer data',
        ));

        self::assertSame(TopicEditorialStatus::SkippedSensitive, $evaluation->status);
        self::assertTrue($evaluation->flags->isSensitive);
        self::assertContains('sensitive_keyword_detected', $evaluation->reasons);
        self::assertContains('avoid playful framing', $evaluation->scenarioNotes->avoid);
    }

    public function testItProducesSkippedLowValueForLowValueInput(): void
    {
        $evaluation = (new FakeTopicEditorialAnalyzer())->analyze($this->draft(
            importance: 1,
            confidence: 0.46,
            contentType: 'privacy_policy',
            publishedAt: CarbonImmutable::parse('2026-05-01T00:00:00Z'),
            fetchedAt: CarbonImmutable::parse('2026-05-25T00:00:00Z'),
        ));

        self::assertSame(TopicEditorialStatus::SkippedLowValue, $evaluation->status);
        self::assertLessThan(45, $evaluation->editorialScore);
        self::assertContains('low_editorial_score', $evaluation->reasons);
    }

    public function testItReturnsStableOutputForSameInput(): void
    {
        $analyzer = new FakeTopicEditorialAnalyzer();
        $draft = $this->draft();

        self::assertSame(
            $analyzer->analyze($draft)->toArray(),
            $analyzer->analyze($draft)->toArray(),
        );
    }

    public function testItDoesNotCallExternalServices(): void
    {
        Http::fake();

        (new FakeTopicEditorialAnalyzer())->analyze($this->draft());

        Http::assertNothingSent();
    }

    public function testToArrayOutputContainsExpectedKeys(): void
    {
        $array = (new FakeTopicEditorialAnalyzer())->analyze($this->draft())->toArray();

        self::assertSame([
            'status',
            'editorial_score',
            'localized',
            'scores',
            'flags',
            'duplicate',
            'scenario_notes',
            'reasons',
            'metadata',
        ], array_keys($array));
        self::assertSame('fake', $array['metadata']['driver']);
        self::assertSame('1.0', $array['metadata']['schema_version']);
    }

    public function testConfiguredInterfaceResolvesFakeAnalyzer(): void
    {
        config(['radiopipe.topic_editorial.analyzer' => 'fake']);

        self::assertInstanceOf(
            FakeTopicEditorialAnalyzer::class,
            $this->app->make(TopicEditorialAnalyzer::class),
        );
    }

    private function draft(
        ?string $title = 'AI chip memory costs',
        ?int $importance = 5,
        ?float $confidence = 0.95,
        ?string $contentType = 'data_analysis_article',
        ?CarbonImmutable $publishedAt = null,
        ?CarbonImmutable $fetchedAt = null,
    ): TopicDraft {
        return new TopicDraft(
            id: 'upstream:213',
            sourceType: 'upstream',
            sourceName: 'Hacker News',
            title: $title,
            originalTitle: $title,
            url: 'https://example.test/article',
            discussionUrl: 'https://news.ycombinator.com/item?id=213',
            summarySeed: 'High-bandwidth memory is becoming a larger share of AI chip component costs.',
            whyItMattersSeed: 'This may affect AI infrastructure cost and supply chains.',
            tags: ['AI chips', 'HBM'],
            entities: ['Example Corp'],
            importance: $importance,
            confidence: $confidence,
            contentType: $contentType,
            limitations: null,
            publishedAt: $publishedAt ?? CarbonImmutable::parse('2026-05-25T10:00:00Z'),
            fetchedAt: $fetchedAt ?? CarbonImmutable::parse('2026-05-25T12:00:00Z'),
            sourceRefs: [
                'provider' => 'digestpipe',
                'upstream_id' => 213,
            ],
        );
    }
}
