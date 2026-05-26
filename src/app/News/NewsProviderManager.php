<?php

namespace App\News;

use InvalidArgumentException;

/**
 * 設定に基づいて News Provider と既定 Query を解決
 */
class NewsProviderManager
{
    /**
     * Provider 名から News Provider を解決して返す
     *
     * @param string|null $driver
     *
     * @return NewsProvider
     */
    public function driver(?string $driver = null): NewsProvider
    {
        $resolvedDriver = $driver ?? $this->stringConfig('radiopipe.news.provider', 'fake');

        return match ($resolvedDriver) {
            'fake' => new FakeNewsProvider(),
            'newsapi' => new NewsApiProvider(
                $this->stringConfig('radiopipe.newsapi.base_url', 'https://newsapi.org'),
                $this->nullableStringConfig('radiopipe.newsapi.api_key'),
                $this->intConfig('radiopipe.news.request_timeout', 10),
                $this->intConfig('radiopipe.news.max_retries', 2),
            ),
            'rss' => new RssFeedProvider(
                $this->intConfig('radiopipe.news.request_timeout', 10),
                $this->intConfig('radiopipe.news.max_retries', 2),
            ),
            default => throw new InvalidArgumentException("Unsupported radiopipe news provider [{$resolvedDriver}]."),
        };
    }

    /**
     * 設定された既定条件の Query を作成して返す
     *
     * @return NewsQuery
     */
    public function defaultQuery(): NewsQuery
    {
        return new NewsQuery(
            country: $this->nullableStringConfig('radiopipe.newsapi.country'),
            category: $this->nullableStringConfig('radiopipe.newsapi.category'),
            language: $this->nullableStringConfig('radiopipe.newsapi.language'),
            pageSize: $this->intConfig('radiopipe.newsapi.page_size', 20),
            sources: $this->nullableStringConfig('radiopipe.newsapi.sources'),
            feedUrls: $this->feedUrls(),
        );
    }

    /**
     * 設定済み Provider でニュースを取得して返す
     *
     * @param NewsQuery|null $query
     *
     * @return list<NewsItem>
     */
    public function fetch(?NewsQuery $query = null): array
    {
        return $this->driver()->fetch($query ?? $this->defaultQuery());
    }

    /**
     * 文字列設定を返す
     *
     * @param string $key
     * @param string $default
     *
     * @return string
     */
    private function stringConfig(string $key, string $default): string
    {
        $value = config($key, $default);

        if (! is_string($value) || $value === '') {
            return $default;
        }

        return $value;
    }

    /**
     * Nullable な文字列設定を返す
     *
     * @param string $key
     *
     * @return string|null
     */
    private function nullableStringConfig(string $key): ?string
    {
        $value = config($key);

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return trim($value);
    }

    /**
     * 整数設定を返す
     *
     * @param string $key
     * @param int $default
     *
     * @return int
     */
    private function intConfig(string $key, int $default): int
    {
        $value = config($key, $default);

        if (! is_int($value)) {
            return $default;
        }

        return $value;
    }

    /**
     * RSS feed URL 設定を返す
     *
     * @return list<string>
     */
    private function feedUrls(): array
    {
        $value = config('radiopipe.rss.feed_urls', []);

        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter(
            $value,
            static fn (mixed $feedUrl): bool => is_string($feedUrl) && trim($feedUrl) !== '',
        ));
    }
}
