<?php

namespace Tests\Feature;

use App\Topics\TopicBuilder;
use App\Upstream\UpstreamArticleItem;
use Carbon\CarbonImmutable;
use Tests\TestCase;

/**
 * @internal
 */
class TopicBuilderTest extends TestCase
{
    public function testBuildsCompleteTopicDraftFromUpstreamArticleItem(): void
    {
        $draft = (new TopicBuilder())->build($this->completeItem());

        self::assertSame('upstream:213', $draft->id);
        self::assertSame('upstream', $draft->sourceType);
        self::assertSame('Hacker News', $draft->sourceName);
        self::assertSame('Memory has grown to nearly two-thirds of AI chip component costs', $draft->title);
        self::assertSame('Memory has grown to nearly two-thirds of AI chip component costs', $draft->originalTitle);
        self::assertSame('https://example.com/article', $draft->url);
        self::assertSame('https://news.ycombinator.com/item?id=48258684', $draft->discussionUrl);
        self::assertSame('Epoch AI estimates that high-bandwidth memory rose to 63% of AI chip component spending by Q4 2025.', $draft->summarySeed);
        self::assertSame('It suggests that memory is becoming the dominant cost driver in AI accelerator supply chains.', $draft->whyItMattersSeed);
        self::assertSame(['AI chips', 'HBM', 'semiconductor supply chain'], $draft->tags);
        self::assertSame(['Epoch AI', 'Nvidia', 'AMD'], $draft->entities);
        self::assertSame(4, $draft->importance);
        self::assertSame(0.96, $draft->confidence);
        self::assertSame('data_analysis_article', $draft->contentType);
        self::assertSame('The figures are model-based estimates, not direct audited costs.', $draft->limitations);
        self::assertSame('2026-05-24 16:31:29', $draft->publishedAt?->toDateTimeString());
        self::assertSame('2026-05-25 05:51:15', $draft->fetchedAt?->toDateTimeString());
        self::assertSame([
            'provider' => 'digestpipe',
            'upstream_id' => 213,
        ], $draft->sourceRefs);
    }

    public function testFallsBackToArticleTitleWhenAnalysisTitlesAreMissing(): void
    {
        $item = $this->completeItem([
            'analysis' => [
                'content' => [
                    'brief' => 'Brief only.',
                ],
            ],
        ]);

        $draft = (new TopicBuilder())->build($item);

        self::assertSame('Article fallback title', $draft->title);
        self::assertSame('Article fallback title', $draft->originalTitle);
    }

    public function testMissingOptionalFieldsAreTolerated(): void
    {
        $item = new UpstreamArticleItem(
            upstreamId: 'minimal-1',
            source: [],
            article: [],
            selection: [],
            analysis: [],
            processing: [],
            fetchedAt: CarbonImmutable::parse('2026-05-25T05:51:15Z'),
            providerName: 'digestpipe',
        );

        $draft = (new TopicBuilder())->build($item);

        self::assertSame('upstream:minimal-1', $draft->id);
        self::assertSame('upstream', $draft->sourceType);
        self::assertNull($draft->sourceName);
        self::assertNull($draft->title);
        self::assertNull($draft->originalTitle);
        self::assertNull($draft->url);
        self::assertNull($draft->discussionUrl);
        self::assertNull($draft->summarySeed);
        self::assertNull($draft->whyItMattersSeed);
        self::assertSame([], $draft->tags);
        self::assertSame([], $draft->entities);
        self::assertNull($draft->importance);
        self::assertNull($draft->confidence);
        self::assertNull($draft->contentType);
        self::assertNull($draft->limitations);
        self::assertNull($draft->publishedAt);
        self::assertSame('2026-05-25 05:51:15', $draft->fetchedAt?->toDateTimeString());
        self::assertSame([
            'provider' => 'digestpipe',
            'upstream_id' => 'minimal-1',
        ], $draft->sourceRefs);
    }

    public function testUsesProcessingAnalyzedAtWhenArticleFetchedAtIsMissing(): void
    {
        $item = $this->completeItem([
            'article' => [
                'title' => 'Article fallback title',
                'published_at' => '2026-05-24T16:31:29Z',
            ],
            'processing' => [
                'analyzed_at' => '2026-05-25T06:12:30Z',
            ],
        ]);

        $draft = (new TopicBuilder())->build($item);

        self::assertSame('2026-05-24 16:31:29', $draft->publishedAt?->toDateTimeString());
        self::assertSame('2026-05-25 06:12:30', $draft->fetchedAt?->toDateTimeString());
    }

    public function testSerializesTopicDraftToArray(): void
    {
        $draft = (new TopicBuilder())->build($this->completeItem());

        self::assertSame([
            'id' => 'upstream:213',
            'source_type' => 'upstream',
            'source_name' => 'Hacker News',
            'title' => 'Memory has grown to nearly two-thirds of AI chip component costs',
            'original_title' => 'Memory has grown to nearly two-thirds of AI chip component costs',
            'url' => 'https://example.com/article',
            'discussion_url' => 'https://news.ycombinator.com/item?id=48258684',
            'summary_seed' => 'Epoch AI estimates that high-bandwidth memory rose to 63% of AI chip component spending by Q4 2025.',
            'why_it_matters_seed' => 'It suggests that memory is becoming the dominant cost driver in AI accelerator supply chains.',
            'tags' => ['AI chips', 'HBM', 'semiconductor supply chain'],
            'entities' => ['Epoch AI', 'Nvidia', 'AMD'],
            'importance' => 4,
            'confidence' => 0.96,
            'content_type' => 'data_analysis_article',
            'limitations' => 'The figures are model-based estimates, not direct audited costs.',
            'published_at' => '2026-05-24T16:31:29.000000Z',
            'fetched_at' => '2026-05-25T05:51:15.000000Z',
            'source_refs' => [
                'provider' => 'digestpipe',
                'upstream_id' => 213,
            ],
        ], $draft->toArray());
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function completeItem(array $overrides = []): UpstreamArticleItem
    {
        $source = [
            'key' => 'hacker_news',
            'name' => 'Hacker News',
            'feed_url' => 'https://news.ycombinator.com/rss',
        ];
        $article = [
            'title' => 'Article fallback title',
            'url' => 'https://example.com/article',
            'discussion_url' => 'https://news.ycombinator.com/item?id=48258684',
            'published_at' => '2026-05-24T16:31:29Z',
            'fetched_at' => '2026-05-25T05:51:15Z',
        ];
        $analysis = [
            'title' => [
                'original' => 'Memory has grown to nearly two-thirds of AI chip component costs',
                'normalized' => 'Memory has grown to nearly two-thirds of AI chip component costs',
            ],
            'content' => [
                'brief' => 'Epoch AI estimates that high-bandwidth memory rose to 63% of AI chip component spending by Q4 2025.',
                'why_it_matters' => 'It suggests that memory is becoming the dominant cost driver in AI accelerator supply chains.',
                'limitations' => 'The figures are model-based estimates, not direct audited costs.',
            ],
            'classification' => [
                'topics' => ['AI chips', 'HBM', 'semiconductor supply chain'],
                'entities' => ['Epoch AI', 'Nvidia', 'AMD'],
                'importance' => 4,
                'confidence' => 0.96,
                'content_type' => 'data_analysis_article',
            ],
        ];
        $processing = [
            'analysis_model' => 'gpt-test',
            'analyzed_at' => '2026-05-25T05:52:30Z',
        ];

        $upstreamId = $overrides['upstreamId'] ?? 213;

        return new UpstreamArticleItem(
            upstreamId: is_int($upstreamId) || is_string($upstreamId) ? $upstreamId : 213,
            source: $this->stringMap($overrides['source'] ?? null, $source),
            article: $this->stringMap($overrides['article'] ?? null, $article),
            selection: $this->stringMap($overrides['selection'] ?? null, [
                'status' => 'selected',
                'score' => 0.91,
            ]),
            analysis: $this->stringMap($overrides['analysis'] ?? null, $analysis),
            processing: $this->stringMap($overrides['processing'] ?? null, $processing),
            fetchedAt: CarbonImmutable::parse('2026-05-25T06:00:00Z'),
            providerName: is_string($overrides['providerName'] ?? null) ? $overrides['providerName'] : 'digestpipe',
        );
    }

    /**
     * @param array<string, mixed> $default
     *
     * @return array<string, mixed>
     */
    private function stringMap(mixed $value, array $default): array
    {
        if (! is_array($value)) {
            return $default;
        }

        return array_filter(
            $value,
            static fn (mixed $key): bool => is_string($key),
            ARRAY_FILTER_USE_KEY,
        );
    }
}
