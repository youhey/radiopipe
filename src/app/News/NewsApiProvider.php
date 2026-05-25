<?php

namespace App\News;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;

/**
 * NewsAPI.org の top-headlines endpoint から一般ニュースを取得する provider です。
 */
class NewsApiProvider implements NewsProvider
{
    private string $baseUrl;

    private ?string $apiKey;

    private int $timeout;

    private int $maxRetries;

    /**
     * Constructor.
     */
    public function __construct(string $baseUrl, ?string $apiKey, int $timeout, int $maxRetries)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->apiKey = $apiKey;
        $this->timeout = $timeout;
        $this->maxRetries = $maxRetries;
    }

    /**
     * NewsAPI response を内部のニュース形式へ正規化します。
     *
     * @return list<NewsItem>
     */
    public function fetch(NewsQuery $query): array
    {
        $parameters = array_filter([
            'country' => $query->sources === null ? $query->country : null,
            'category' => $query->sources === null ? $query->category : null,
            'language' => $query->language,
            'q' => $query->query,
            'pageSize' => $query->pageSize,
            'sources' => $query->sources,
        ], static fn (mixed $value): bool => $value !== null && $value !== '');

        $response = Http::baseUrl($this->baseUrl)
            ->acceptJson()
            ->when($this->apiKey !== null && $this->apiKey !== '', function ($request) {
                return $request->withHeader('X-Api-Key', $this->apiKey);
            })
            ->timeout($this->timeout)
            ->retry($this->maxRetries, 100, null, false)
            ->get('/v2/top-headlines', $parameters);

        if ($response->failed()) {
            throw NewsProviderException::failedHttpResponse('newsapi', $response->status());
        }

        $payload = $response->json();

        if (! is_array($payload) || ($payload['status'] ?? null) !== 'ok') {
            throw NewsProviderException::invalidResponse('newsapi');
        }

        $articles = $payload['articles'] ?? null;

        if (! is_array($articles)) {
            throw NewsProviderException::invalidResponse('newsapi');
        }

        $fetchedAt = CarbonImmutable::now('UTC');
        $items = [];

        foreach ($articles as $article) {
            if (! is_array($article)) {
                continue;
            }

            $title = $this->nonEmptyString($article['title'] ?? null);
            $url = $this->nonEmptyString($article['url'] ?? null);

            if ($title === null || $url === null) {
                continue;
            }

            $source = $article['source'] ?? null;
            $sourceName = is_array($source) ? $this->nonEmptyString($source['name'] ?? null) : null;

            $items[] = new NewsItem(
                providerName: 'newsapi',
                sourceName: $sourceName,
                sourceUrl: null,
                title: $title,
                url: $url,
                summary: $this->sanitizeSummary($article['description'] ?? null),
                author: $this->nonEmptyString($article['author'] ?? null),
                category: $query->category,
                language: $query->language,
                country: $query->country,
                publishedAt: $this->parseTime($article['publishedAt'] ?? null),
                fetchedAt: $fetchedAt,
                sourceLabel: 'NewsAPI.org',
            );
        }

        return $items;
    }

    /**
     * 空ではない文字列値を返します。
     */
    private function nonEmptyString(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return trim($value);
    }

    /**
     * provider の概要テキストを内部 summary として整えます。
     */
    private function sanitizeSummary(mixed $value): ?string
    {
        $summary = $this->nonEmptyString($value);

        if ($summary === null) {
            return null;
        }

        return trim(html_entity_decode(strip_tags($summary), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    /**
     * provider の時刻文字列を CarbonImmutable へ変換します。
     */
    private function parseTime(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return CarbonImmutable::parse($value);
    }
}
