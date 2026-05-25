<?php

namespace Tests\Feature;

use App\News\FakeNewsProvider;
use App\News\NewsApiProvider;
use App\News\NewsProvider;
use App\News\NewsProviderException;
use App\News\NewsProviderManager;
use App\News\NewsQuery;
use App\News\RssFeedProvider;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * @internal
 */
class NewsProviderTest extends TestCase
{
    public function testFakeProviderReturnsNormalizedNewsItem(): void
    {
        $report = (new FakeNewsProvider())->fetch(new NewsQuery(
            country: 'jp',
            category: 'general',
            language: 'ja',
        ));

        self::assertCount(1, $report);

        $item = $report[0];

        self::assertSame('fake', $item->providerName);
        self::assertSame('Fake News Source', $item->sourceName);
        self::assertSame('https://example.test/news', $item->sourceUrl);
        self::assertSame('Fake general news item', $item->title);
        self::assertSame('https://example.test/news/fake-general-news-item', $item->url);
        self::assertSame('Fake provider summary for local development.', $item->summary);
        self::assertNull($item->author);
        self::assertSame('general', $item->category);
        self::assertSame('ja', $item->language);
        self::assertSame('jp', $item->country);
        self::assertSame('Fake news provider', $item->sourceLabel);
        self::assertInstanceOf(CarbonImmutable::class, $item->publishedAt);
        self::assertInstanceOf(CarbonImmutable::class, $item->fetchedAt);
    }

    public function testNewsProviderCanBeSelectedThroughConfig(): void
    {
        config(['radiopipe.news.provider' => 'fake']);

        self::assertInstanceOf(FakeNewsProvider::class, $this->app->make(NewsProvider::class));

        config(['radiopipe.news.provider' => 'newsapi']);

        self::assertInstanceOf(NewsApiProvider::class, $this->app->make(NewsProvider::class));

        config(['radiopipe.news.provider' => 'rss']);

        self::assertInstanceOf(RssFeedProvider::class, $this->app->make(NewsProvider::class));
    }

    public function testNewsProviderManagerBuildsDefaultQueryFromConfig(): void
    {
        config([
            'radiopipe.newsapi.country' => 'jp',
            'radiopipe.newsapi.category' => 'business',
            'radiopipe.newsapi.language' => 'ja',
            'radiopipe.newsapi.page_size' => 5,
            'radiopipe.newsapi.sources' => 'test-source',
            'radiopipe.rss.feed_urls' => [
                'https://example.test/feed.xml',
            ],
        ]);

        $query = $this->app->make(NewsProviderManager::class)->defaultQuery();

        self::assertSame('jp', $query->country);
        self::assertSame('business', $query->category);
        self::assertSame('ja', $query->language);
        self::assertSame(5, $query->pageSize);
        self::assertSame('test-source', $query->sources);
        self::assertSame(['https://example.test/feed.xml'], $query->feedUrls);
    }

    public function testNewsApiProviderFetchesAndNormalizesNewsItems(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-05-25 12:00:00', 'UTC'));

        config([
            'radiopipe.news.provider' => 'newsapi',
            'radiopipe.newsapi.base_url' => 'https://newsapi.test',
            'radiopipe.newsapi.api_key' => 'test-newsapi-key',
            'radiopipe.news.request_timeout' => 7,
            'radiopipe.news.max_retries' => 1,
        ]);

        Http::fake([
            'https://newsapi.test/v2/top-headlines*' => Http::response([
                'status' => 'ok',
                'totalResults' => 1,
                'articles' => [
                    [
                        'source' => [
                            'id' => 'test-source',
                            'name' => 'Test Source',
                        ],
                        'author' => 'Reporter Name',
                        'title' => 'General headline',
                        'description' => '<p>Short &amp; safe summary.</p>',
                        'url' => 'https://example.test/articles/general-headline',
                        'publishedAt' => '2026-05-25T11:30:00Z',
                    ],
                ],
            ], 200),
        ]);

        try {
            $items = $this->app->make(NewsProvider::class)->fetch(new NewsQuery(
                query: 'economy',
                country: 'jp',
                category: 'general',
                language: 'ja',
                pageSize: 10,
            ));

            self::assertCount(1, $items);

            $item = $items[0];

            self::assertSame('newsapi', $item->providerName);
            self::assertSame('Test Source', $item->sourceName);
            self::assertNull($item->sourceUrl);
            self::assertSame('General headline', $item->title);
            self::assertSame('https://example.test/articles/general-headline', $item->url);
            self::assertSame('Short & safe summary.', $item->summary);
            self::assertSame('Reporter Name', $item->author);
            self::assertSame('general', $item->category);
            self::assertSame('ja', $item->language);
            self::assertSame('jp', $item->country);
            self::assertSame('2026-05-25 11:30:00', $item->publishedAt?->toDateTimeString());
            self::assertSame('2026-05-25 12:00:00', $item->fetchedAt->toDateTimeString());
            self::assertSame('NewsAPI.org', $item->sourceLabel);

            Http::assertSent(function (Request $request): bool {
                $url = $request->url();

                return str_starts_with($url, 'https://newsapi.test/v2/top-headlines')
                    && str_contains($url, 'country=jp')
                    && str_contains($url, 'category=general')
                    && str_contains($url, 'language=ja')
                    && str_contains($url, 'q=economy')
                    && str_contains($url, 'pageSize=10')
                    && $request->hasHeader('X-Api-Key', 'test-newsapi-key');
            });
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function testNewsApiProviderThrowsForFailedHttpResponse(): void
    {
        config([
            'radiopipe.news.provider' => 'newsapi',
            'radiopipe.newsapi.base_url' => 'https://newsapi.test',
            'radiopipe.newsapi.api_key' => 'test-newsapi-key',
        ]);

        Http::fake([
            'https://newsapi.test/v2/top-headlines*' => Http::response(['status' => 'error'], 429),
        ]);

        $this->expectException(NewsProviderException::class);
        $this->expectExceptionMessage('News provider [newsapi] returned HTTP status [429].');

        $this->app->make(NewsProvider::class)->fetch(new NewsQuery(country: 'jp'));
    }

    public function testRssProviderFetchesAndNormalizesFeedItems(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-05-25 12:00:00', 'UTC'));

        config([
            'radiopipe.news.provider' => 'rss',
            'radiopipe.news.request_timeout' => 7,
            'radiopipe.news.max_retries' => 1,
        ]);

        Http::fake([
            'https://example.test/feed.xml' => Http::response($this->rssFixture(), 200, [
                'Content-Type' => 'application/rss+xml',
            ]),
        ]);

        try {
            $items = $this->app->make(NewsProvider::class)->fetch(new NewsQuery(
                country: 'jp',
                language: 'ja',
                feedUrls: ['https://example.test/feed.xml'],
            ));

            self::assertCount(2, $items);

            $first = $items[0];

            self::assertSame('rss', $first->providerName);
            self::assertSame('Configured RSS Source', $first->sourceName);
            self::assertSame('https://example.test/', $first->sourceUrl);
            self::assertSame('RSS item with optional fields', $first->title);
            self::assertSame('https://example.test/articles/1', $first->url);
            self::assertSame('Summary with markup.', $first->summary);
            self::assertSame('author@example.test', $first->author);
            self::assertSame('local', $first->category);
            self::assertSame('ja', $first->language);
            self::assertSame('jp', $first->country);
            self::assertSame('2026-05-25 10:00:00', $first->publishedAt?->setTimezone('UTC')->toDateTimeString());
            self::assertSame('2026-05-25 12:00:00', $first->fetchedAt->toDateTimeString());
            self::assertSame('Configured RSS Source', $first->sourceLabel);

            $second = $items[1];

            self::assertSame('RSS item with missing optional fields', $second->title);
            self::assertSame('https://example.test/articles/2', $second->url);
            self::assertNull($second->summary);
            self::assertNull($second->author);
            self::assertNull($second->category);
            self::assertNull($second->publishedAt);

            Http::assertSent(fn (Request $request): bool => $request->url() === 'https://example.test/feed.xml');
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function testRssProviderThrowsForFailedHttpResponse(): void
    {
        config(['radiopipe.news.provider' => 'rss']);

        Http::fake([
            'https://example.test/feed.xml' => Http::response('Service unavailable', 503),
        ]);

        $this->expectException(NewsProviderException::class);
        $this->expectExceptionMessage('News provider [rss] returned HTTP status [503].');

        $this->app->make(NewsProvider::class)->fetch(new NewsQuery(
            feedUrls: ['https://example.test/feed.xml'],
        ));
    }

    private function rssFixture(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0">
    <channel>
        <title>Configured RSS Source</title>
        <link>https://example.test/</link>
        <item>
            <title>RSS item with optional fields</title>
            <link>https://example.test/articles/1</link>
            <description><![CDATA[<p>Summary <strong>with</strong> markup.</p>]]></description>
            <author>author@example.test</author>
            <category>local</category>
            <pubDate>Mon, 25 May 2026 19:00:00 +0900</pubDate>
        </item>
        <item>
            <title>RSS item with missing optional fields</title>
            <link>https://example.test/articles/2</link>
        </item>
    </channel>
</rss>
XML;
    }
}
