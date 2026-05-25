<?php

namespace Tests\Unit\Topics\Editorial;

use App\Topics\Editorial\TopicDuplicateCandidateDetector;
use App\Topics\TopicDraft;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class TopicDuplicateCandidateDetectorTest extends TestCase
{
    public function testExactUrlMatchReturnsStrongDuplicateCandidate(): void
    {
        $assessment = $this->detector()->assess(
            $this->draft(id: 'topic-a', url: 'https://example.test/article#comments'),
            $this->draft(id: 'topic-b', url: 'https://example.test/article'),
        );

        self::assertSame(100, $assessment->duplicateScore);
        self::assertTrue($assessment->isStrongDuplicate());
        self::assertSame('exact normalized URL match', $assessment->reason);
    }

    public function testExactDiscussionUrlMatchReturnsStrongDuplicateCandidate(): void
    {
        $assessment = $this->detector()->assess(
            $this->draft(id: 'topic-a', url: 'https://example.test/a', discussionUrl: 'https://news.ycombinator.com/item?id=123'),
            $this->draft(id: 'topic-b', url: 'https://example.test/b', discussionUrl: 'https://news.ycombinator.com/item?id=123#reply'),
        );

        self::assertSame(95, $assessment->duplicateScore);
        self::assertTrue($assessment->isStrongDuplicate());
        self::assertSame('exact normalized discussion URL match', $assessment->reason);
    }

    public function testExactNormalizedTitleMatchReturnsStrongDuplicateCandidate(): void
    {
        $assessment = $this->detector()->assess(
            $this->draft(id: 'topic-a', title: 'AI Chip Memory Costs!', url: 'https://example.test/a', discussionUrl: null),
            $this->draft(id: 'topic-b', title: 'ai chip memory costs', url: 'https://example.test/b', discussionUrl: null),
        );

        self::assertSame(90, $assessment->duplicateScore);
        self::assertTrue($assessment->isStrongDuplicate());
        self::assertTrue($assessment->signals['exact_title_match']);
    }

    public function testSimilarTitlesProduceCandidateWhenAboveThreshold(): void
    {
        $assessment = $this->detector()->assess(
            $this->draft(
                id: 'topic-a',
                title: 'AI chip memory cost pressure rises',
                summarySeed: 'HBM memory costs are rising for AI chips',
                url: 'https://example.test/a',
                discussionUrl: null,
                tags: ['AI chips', 'HBM'],
                entities: ['Nvidia'],
            ),
            $this->draft(
                id: 'topic-b',
                title: 'AI chip memory cost pressure increases',
                summarySeed: 'HBM memory costs are increasing for AI chips',
                url: 'https://example.test/b',
                discussionUrl: null,
                tags: ['AI chips', 'HBM'],
                entities: ['Nvidia'],
            ),
        );

        self::assertGreaterThanOrEqual(TopicDuplicateCandidateDetector::CANDIDATE_SCORE, $assessment->duplicateScore);
        self::assertTrue($assessment->isCandidate());
        self::assertSame('similar deterministic topic signals', $assessment->reason);
    }

    public function testUnrelatedTopicsDoNotProduceCandidates(): void
    {
        $topics = [
            $this->draft(id: 'topic-a', title: 'AI chip memory costs', url: 'https://example.test/a', discussionUrl: null, tags: ['AI chips'], entities: ['Nvidia']),
            $this->draft(id: 'topic-b', title: 'New CSS layout proposal', url: 'https://example.test/b', discussionUrl: null, tags: ['CSS'], entities: ['W3C']),
        ];

        self::assertSame([], $this->detector()->detectCandidates($topics));
    }

    public function testEntityAndTagOverlapAloneDoesNotCreateStrongDuplicate(): void
    {
        $assessment = $this->detector()->assess(
            $this->draft(id: 'topic-a', title: 'AI chip memory costs', url: 'https://example.test/a', discussionUrl: null, tags: ['AI chips', 'HBM'], entities: ['Nvidia', 'AMD']),
            $this->draft(id: 'topic-b', title: 'GPU driver release notes', url: 'https://example.test/b', discussionUrl: null, tags: ['AI chips', 'HBM'], entities: ['Nvidia', 'AMD']),
        );

        self::assertFalse($assessment->isStrongDuplicate());
    }

    public function testEmptyFieldsDoNotCrash(): void
    {
        $assessment = $this->detector()->assess(
            $this->draft(id: 'topic-a', title: null, url: null, discussionUrl: null, summarySeed: null, tags: [], entities: []),
            $this->draft(id: 'topic-b', title: null, url: null, discussionUrl: null, summarySeed: null, tags: [], entities: []),
        );

        self::assertSame(0, $assessment->duplicateScore);
        self::assertFalse($assessment->isCandidate());
    }

    public function testCanonicalKeyIsDeterministic(): void
    {
        $detector = $this->detector();
        $draft = $this->draft(id: 'topic-a', title: 'AI Chip Memory Costs', url: 'https://example.test/articles/ai-memory#comments');

        self::assertSame($detector->canonicalKey($draft), $detector->canonicalKey($draft));
        self::assertStringStartsWith('url-', (string) $detector->canonicalKey($draft));
    }

    public function testCandidateDetectionOutputIsStableForSameInput(): void
    {
        $detector = $this->detector();
        $topics = [
            $this->draft(id: 'topic-a', url: 'https://example.test/article'),
            $this->draft(id: 'topic-b', url: 'https://example.test/article#section'),
            $this->draft(id: 'topic-c', title: 'Unrelated browser feature'),
        ];

        self::assertSame(
            $detector->detectCandidates($topics),
            $detector->detectCandidates($topics),
        );
    }

    private function detector(): TopicDuplicateCandidateDetector
    {
        return new TopicDuplicateCandidateDetector();
    }

    /**
     * @param list<string> $tags
     * @param list<string> $entities
     */
    private function draft(
        string $id,
        ?string $title = 'AI chip memory costs',
        ?string $url = 'https://example.test/article',
        ?string $discussionUrl = 'https://news.ycombinator.com/item?id=213',
        ?string $summarySeed = 'High-bandwidth memory is becoming a larger share of AI chip component costs.',
        array $tags = ['AI chips'],
        array $entities = ['Example Corp'],
    ): TopicDraft {
        return new TopicDraft(
            id: $id,
            sourceType: 'upstream',
            sourceName: 'Hacker News',
            title: $title,
            originalTitle: $title,
            url: $url,
            discussionUrl: $discussionUrl,
            summarySeed: $summarySeed,
            whyItMattersSeed: 'This may affect AI infrastructure cost and supply chains.',
            tags: $tags,
            entities: $entities,
            importance: 4,
            confidence: 0.95,
            contentType: 'data_analysis_article',
            limitations: null,
            publishedAt: CarbonImmutable::parse('2026-05-25T10:00:00Z'),
            fetchedAt: CarbonImmutable::parse('2026-05-25T12:00:00Z'),
            sourceRefs: [
                'provider' => 'digestpipe',
                'upstream_id' => $id,
            ],
        );
    }
}
