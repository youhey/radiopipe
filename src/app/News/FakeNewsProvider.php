<?php

namespace App\News;

use Carbon\CarbonImmutable;

/**
 * テストとローカル開発用の固定ニュース provider です。
 */
class FakeNewsProvider implements NewsProvider
{
    /**
     * deterministic な fake 一般ニュース項目を返します。
     *
     * @return list<NewsItem>
     */
    public function fetch(NewsQuery $query): array
    {
        $now = CarbonImmutable::now('UTC');

        return [
            new NewsItem(
                providerName: 'fake',
                sourceName: 'Fake News Source',
                sourceUrl: 'https://example.test/news',
                title: 'Fake general news item',
                url: 'https://example.test/news/fake-general-news-item',
                summary: 'Fake provider summary for local development.',
                author: null,
                category: $query->category,
                language: $query->language,
                country: $query->country,
                publishedAt: $now,
                fetchedAt: $now,
                sourceLabel: 'Fake news provider',
            ),
        ];
    }
}
