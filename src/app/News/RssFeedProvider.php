<?php

namespace App\News;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use SimpleXMLElement;
use Throwable;

/**
 * 設定された RSS/Atom feed から一般ニュース項目を取得する provider です。
 */
class RssFeedProvider implements NewsProvider
{
    private int $timeout;

    private int $maxRetries;

    /**
     * Constructor.
     */
    public function __construct(int $timeout, int $maxRetries)
    {
        $this->timeout = $timeout;
        $this->maxRetries = $maxRetries;
    }

    /**
     * RSS/Atom feed item を内部のニュース形式へ正規化します。
     *
     * @return list<NewsItem>
     */
    public function fetch(NewsQuery $query): array
    {
        $items = [];
        $fetchedAt = CarbonImmutable::now('UTC');

        foreach ($query->feedUrls as $feedUrl) {
            $response = Http::accept('application/rss+xml, application/atom+xml, application/xml, text/xml, */*')
                ->timeout($this->timeout)
                ->retry($this->maxRetries, 100, null, false)
                ->get($feedUrl);

            if ($response->failed()) {
                throw NewsProviderException::failedHttpResponse('rss', $response->status());
            }

            $items = [
                ...$items,
                ...$this->parseFeed($response->body(), $feedUrl, $query, $fetchedAt),
            ];
        }

        return $items;
    }

    /**
     * XML feed body をニュース項目へ変換します。
     *
     * @return list<NewsItem>
     */
    private function parseFeed(string $body, string $feedUrl, NewsQuery $query, CarbonImmutable $fetchedAt): array
    {
        $previous = libxml_use_internal_errors(true);

        try {
            $xml = simplexml_load_string($body);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        if (! $xml instanceof SimpleXMLElement) {
            throw NewsProviderException::invalidResponse('rss');
        }

        if (isset($xml->channel)) {
            return $this->parseRss($xml, $feedUrl, $query, $fetchedAt);
        }

        if ($xml->getName() === 'feed') {
            return $this->parseAtom($xml, $feedUrl, $query, $fetchedAt);
        }

        throw NewsProviderException::invalidResponse('rss');
    }

    /**
     * RSS 2.0 item をニュース項目へ変換します。
     *
     * @return list<NewsItem>
     */
    private function parseRss(SimpleXMLElement $xml, string $feedUrl, NewsQuery $query, CarbonImmutable $fetchedAt): array
    {
        $channel = $xml->channel;
        $sourceName = $this->text($channel->title ?? null);
        $sourceUrl = $this->text($channel->link ?? null) ?? $feedUrl;
        $items = [];

        foreach ($channel->item as $item) {
            $title = $this->text($item->title ?? null);
            $url = $this->text($item->link ?? null);

            if ($title === null || $url === null) {
                continue;
            }

            $items[] = new NewsItem(
                providerName: 'rss',
                sourceName: $sourceName,
                sourceUrl: $sourceUrl,
                title: $title,
                url: $url,
                summary: $this->sanitizeSummary($item->description ?? null),
                author: $this->text($item->author ?? null),
                category: $this->text($item->category ?? null) ?? $query->category,
                language: $query->language,
                country: $query->country,
                publishedAt: $this->parseTime($this->text($item->pubDate ?? null)),
                fetchedAt: $fetchedAt,
                sourceLabel: $sourceName ?? $feedUrl,
            );
        }

        return $items;
    }

    /**
     * Atom entry をニュース項目へ変換します。
     *
     * @return list<NewsItem>
     */
    private function parseAtom(SimpleXMLElement $xml, string $feedUrl, NewsQuery $query, CarbonImmutable $fetchedAt): array
    {
        $sourceName = $this->text($xml->title ?? null);
        $sourceUrl = $this->atomLink($xml) ?? $feedUrl;
        $items = [];

        foreach ($xml->entry as $entry) {
            $title = $this->text($entry->title ?? null);
            $url = $this->atomLink($entry);

            if ($title === null || $url === null) {
                continue;
            }

            $items[] = new NewsItem(
                providerName: 'rss',
                sourceName: $sourceName,
                sourceUrl: $sourceUrl,
                title: $title,
                url: $url,
                summary: $this->sanitizeSummary($entry->summary ?? $entry->content ?? null),
                author: $this->text($entry->author->name ?? null),
                category: $this->atomCategory($entry) ?? $query->category,
                language: $query->language,
                country: $query->country,
                publishedAt: $this->parseTime($this->text($entry->published ?? null) ?? $this->text($entry->updated ?? null)),
                fetchedAt: $fetchedAt,
                sourceLabel: $sourceName ?? $feedUrl,
            );
        }

        return $items;
    }

    /**
     * XML 要素の文字列値を取得します。
     */
    private function text(mixed $value): ?string
    {
        if (! $value instanceof SimpleXMLElement) {
            return null;
        }

        $text = trim((string) $value);

        if ($text === '') {
            return null;
        }

        return $text;
    }

    /**
     * RSS/Atom summary から HTML を取り除きます。
     */
    private function sanitizeSummary(mixed $value): ?string
    {
        $summary = $this->text($value);

        if ($summary === null) {
            return null;
        }

        $summary = trim(html_entity_decode(strip_tags($summary), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        return $summary === '' ? null : $summary;
    }

    /**
     * Atom link の href を取得します。
     */
    private function atomLink(SimpleXMLElement $element): ?string
    {
        foreach ($element->link as $link) {
            $attributes = $link->attributes();
            $href = isset($attributes['href']) ? trim((string) $attributes['href']) : '';

            if ($href === '') {
                continue;
            }

            $rel = isset($attributes['rel']) ? trim((string) $attributes['rel']) : 'alternate';

            if ($rel === 'alternate') {
                return $href;
            }
        }

        return null;
    }

    /**
     * Atom category の term を取得します。
     */
    private function atomCategory(SimpleXMLElement $entry): ?string
    {
        $category = $entry->category[0] ?? null;

        if (! $category instanceof SimpleXMLElement) {
            return null;
        }

        $attributes = $category->attributes();
        $term = isset($attributes['term']) ? trim((string) $attributes['term']) : '';

        return $term === '' ? null : $term;
    }

    /**
     * provider の時刻文字列を CarbonImmutable へ変換します。
     */
    private function parseTime(?string $value): ?CarbonImmutable
    {
        if ($value === null) {
            return null;
        }

        try {
            return CarbonImmutable::parse($value);
        } catch (Throwable) {
            return null;
        }
    }
}
