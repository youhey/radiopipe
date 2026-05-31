<?php

namespace Tests\Feature;

use App\Upstream\DigestpipeUpstreamProvider;
use App\Upstream\FakeUpstreamProvider;
use App\Upstream\UpstreamArticleQuery;
use App\Upstream\UpstreamProvider;
use App\Upstream\UpstreamProviderException;
use App\Upstream\UpstreamProviderManager;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * @internal
 */
class UpstreamProviderTest extends TestCase
{
    public function testFakeProviderReturnsNormalizedUpstreamArticleItem(): void
    {
        $items = (new FakeUpstreamProvider())->fetch(new UpstreamArticleQuery(source: 'hacker_news'));

        self::assertCount(1, $items);

        $item = $items[0];

        self::assertSame('fake', $item->providerName);
        self::assertSame('fake-1', $item->upstreamId);
        self::assertSame('hacker_news', $item->source['key'] ?? null);
        self::assertSame('Fake Digest Source', $item->source['name'] ?? null);
        self::assertSame('Fake completed digest article', $item->article['title'] ?? null);
        self::assertSame('selected', $item->selection['status'] ?? null);
        $content = $item->analysis['content'] ?? null;
        self::assertIsArray($content);
        self::assertSame('Fake upstream digest brief.', $content['brief'] ?? null);
        self::assertSame('fake', $item->processing['analysis_model'] ?? null);
        self::assertInstanceOf(CarbonImmutable::class, $item->fetchedAt);
    }

    public function testUpstreamProviderCanBeSelectedThroughConfig(): void
    {
        config(['radiopipe.upstream.provider' => 'fake']);

        self::assertInstanceOf(FakeUpstreamProvider::class, $this->app->make(UpstreamProvider::class));

        config([
            'radiopipe.upstream.provider' => 'digestpipe',
            'radiopipe.upstream.url' => 'https://digestpipe.test',
        ]);

        self::assertInstanceOf(DigestpipeUpstreamProvider::class, $this->app->make(UpstreamProvider::class));
    }

    public function testUpstreamProviderManagerBuildsDefaultQueryFromConfig(): void
    {
        config([
            'radiopipe.upstream.default_window_hours' => 12,
            'radiopipe.upstream.default_limit' => 50,
        ]);

        $query = $this->app->make(UpstreamProviderManager::class)
            ->defaultQuery(CarbonImmutable::parse('2026-05-25T12:00:00Z'));

        self::assertSame('2026-05-25T00:00:00.000000Z', $query->from?->toJSON());
        self::assertSame('2026-05-25T12:00:00.000000Z', $query->to?->toJSON());
        self::assertSame(50, $query->limit);
        self::assertNull($query->source);
    }

    public function testDigestpipeProviderFetchesAndNormalizesUpstreamArticles(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-05-25 12:00:00', 'UTC'));

        config([
            'radiopipe.upstream.provider' => 'digestpipe',
            'radiopipe.upstream.url' => 'https://digestpipe.test',
            'radiopipe.upstream.key' => 'test-upstream-token',
            'radiopipe.upstream.request_timeout' => 7,
            'radiopipe.upstream.max_retries' => 1,
        ]);

        Http::fake([
            'https://digestpipe.test/api/articles*' => Http::response([
                'articles' => [
                    [
                        'id' => 123,
                        'source' => [
                            'key' => 'hacker_news',
                            'name' => 'Hacker News',
                        ],
                        'article' => [
                            'title' => 'Digest item title',
                            'url' => 'https://example.test/article',
                            'published_at' => '2026-05-25T11:00:00Z',
                        ],
                        'selection' => [
                            'status' => 'selected',
                            'score' => 0.91,
                        ],
                        'analysis' => [
                            'content' => [
                                'brief' => 'Structured digest brief.',
                            ],
                            'classification' => [
                                'topics' => ['technology'],
                            ],
                        ],
                        'processing' => [
                            'analysis_model' => 'gpt-test',
                            'analyzed_at' => '2026-05-25T11:30:00Z',
                        ],
                    ],
                ],
                'meta' => [
                    'count' => 1,
                ],
            ], 200),
        ]);

        try {
            $items = $this->app->make(UpstreamProvider::class)->fetch(new UpstreamArticleQuery(
                from: CarbonImmutable::parse('2026-05-25T00:00:00Z'),
                to: CarbonImmutable::parse('2026-05-25T12:00:00Z'),
                source: 'hacker_news',
                limit: 10,
            ));

            self::assertCount(1, $items);

            $item = $items[0];

            self::assertSame('digestpipe', $item->providerName);
            self::assertSame(123, $item->upstreamId);
            self::assertSame('hacker_news', $item->source['key'] ?? null);
            self::assertSame('Hacker News', $item->source['name'] ?? null);
            self::assertSame('Digest item title', $item->article['title'] ?? null);
            self::assertSame('https://example.test/article', $item->article['url'] ?? null);
            self::assertSame('selected', $item->selection['status'] ?? null);
            self::assertSame(0.91, $item->selection['score'] ?? null);
            $content = $item->analysis['content'] ?? null;
            self::assertIsArray($content);
            self::assertSame('Structured digest brief.', $content['brief'] ?? null);
            $classification = $item->analysis['classification'] ?? null;
            self::assertIsArray($classification);
            self::assertSame(['technology'], $classification['topics'] ?? null);
            self::assertSame('gpt-test', $item->processing['analysis_model'] ?? null);
            self::assertSame('2026-05-25 12:00:00', $item->fetchedAt->toDateTimeString());

            Http::assertSent(function (Request $request): bool {
                $url = $request->url();

                return str_starts_with($url, 'https://digestpipe.test/api/articles')
                    && str_contains($url, 'from=2026-05-25T00%3A00%3A00.000000Z')
                    && str_contains($url, 'to=2026-05-25T12%3A00%3A00.000000Z')
                    && str_contains($url, 'source=hacker_news')
                    && str_contains($url, 'limit=10')
                    && $request->hasHeader('Authorization', 'Bearer test-upstream-token');
            });
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function testDigestpipeProviderToleratesMissingOptionalFields(): void
    {
        config([
            'radiopipe.upstream.provider' => 'digestpipe',
            'radiopipe.upstream.url' => 'https://digestpipe.test',
        ]);

        Http::fake([
            'https://digestpipe.test/api/articles*' => Http::response([
                'articles' => [
                    [
                        'id' => 'external-abc',
                        'article' => [
                            'title' => 'Minimal digest item',
                        ],
                    ],
                ],
            ], 200),
        ]);

        $items = $this->app->make(UpstreamProvider::class)->fetch(new UpstreamArticleQuery(limit: 1));

        self::assertCount(1, $items);
        self::assertSame('external-abc', $items[0]->upstreamId);
        self::assertSame([], $items[0]->source);
        self::assertSame(['title' => 'Minimal digest item'], $items[0]->article);
        self::assertSame([], $items[0]->selection);
        self::assertSame([], $items[0]->analysis);
        self::assertSame([], $items[0]->processing);
    }

    public function testDigestpipeProviderThrowsForFailedHttpResponse(): void
    {
        config([
            'radiopipe.upstream.provider' => 'digestpipe',
            'radiopipe.upstream.url' => 'https://digestpipe.test',
            'radiopipe.upstream.key' => 'test-upstream-token',
        ]);

        Http::fake([
            'https://digestpipe.test/api/articles*' => Http::response(['message' => 'Unauthorized'], 401),
        ]);

        $this->expectException(UpstreamProviderException::class);
        $this->expectExceptionMessage('Upstream provider [digestpipe] returned HTTP status [401].');

        $this->app->make(UpstreamProvider::class)->fetch(new UpstreamArticleQuery(limit: 10));
    }
}
