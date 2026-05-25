<?php

namespace App\Topics;

use App\Upstream\UpstreamArticleItem;
use Carbon\CarbonImmutable;
use Throwable;

/**
 * UpstreamArticleItem を TopicDraft へ変換します。
 */
class TopicBuilder
{
    /**
     * upstream 記事から deterministic に TopicDraft を作成します。
     */
    public function build(UpstreamArticleItem $item): TopicDraft
    {
        $analysisTitle = $this->arrayValue($item->analysis['title'] ?? null);
        $analysisContent = $this->arrayValue($item->analysis['content'] ?? null);
        $classification = $this->arrayValue($item->analysis['classification'] ?? null);
        $articleTitle = $this->stringValue($item->article['title'] ?? null);

        return new TopicDraft(
            id: 'upstream:' . $item->upstreamId,
            sourceType: 'upstream',
            sourceName: $this->stringValue($item->source['name'] ?? null),
            title: $this->stringValue($analysisTitle['normalized'] ?? null) ?? $articleTitle,
            originalTitle: $this->stringValue($analysisTitle['original'] ?? null) ?? $articleTitle,
            url: $this->stringValue($item->article['url'] ?? null),
            discussionUrl: $this->stringValue($item->article['discussion_url'] ?? null),
            summarySeed: $this->stringValue($analysisContent['brief'] ?? null),
            whyItMattersSeed: $this->stringValue($analysisContent['why_it_matters'] ?? null),
            tags: $this->stringList($classification['topics'] ?? null),
            entities: $this->stringList($classification['entities'] ?? null),
            importance: $this->importance($classification['importance'] ?? null),
            confidence: $this->floatValue($classification['confidence'] ?? null),
            contentType: $this->stringValue($classification['content_type'] ?? null),
            limitations: $this->stringValue($analysisContent['limitations'] ?? null),
            publishedAt: $this->dateTime($item->article['published_at'] ?? null),
            fetchedAt: $this->dateTime($item->article['fetched_at'] ?? null)
                ?? $this->dateTime($item->processing['analyzed_at'] ?? null)
                ?? $item->fetchedAt,
            sourceRefs: [
                'provider' => $item->providerName,
                'upstream_id' => $item->upstreamId,
            ],
            upstreamSelection: [
                'status' => $this->stringValue($item->selection['status'] ?? null),
                'score' => $this->intValue($item->selection['score'] ?? null),
            ],
        );
    }

    /**
     * object-like 配列値を取得します。
     *
     * @return array<string, mixed>
     */
    private function arrayValue(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_filter(
            $value,
            static fn (mixed $key): bool => is_string($key),
            ARRAY_FILTER_USE_KEY,
        );
    }

    /**
     * 空ではない文字列値を取得します。
     */
    private function stringValue(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return trim($value);
    }

    /**
     * 文字列配列を取得します。
     *
     * @return list<string>
     */
    private function stringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter(
            array_map(
                fn (mixed $item): ?string => $this->stringValue($item),
                $value,
            ),
            static fn (?string $item): bool => $item !== null,
        ));
    }

    /**
     * importance 値を数値として取得します。
     */
    private function importance(mixed $value): float|int|null
    {
        if (is_int($value) || is_float($value)) {
            return $value;
        }

        if (is_string($value) && is_numeric($value)) {
            $floatValue = (float) $value;

            return floor($floatValue) === $floatValue ? (int) $floatValue : $floatValue;
        }

        return null;
    }

    /**
     * float 値を取得します。
     */
    private function floatValue(mixed $value): ?float
    {
        if (! is_int($value) && ! is_float($value) && ! is_string($value)) {
            return null;
        }

        if (! is_numeric($value)) {
            return null;
        }

        return (float) $value;
    }

    /**
     * int 値を取得します。
     */
    private function intValue(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && filter_var($value, FILTER_VALIDATE_INT) !== false) {
            return (int) $value;
        }

        return null;
    }

    /**
     * 日時文字列を CarbonImmutable へ変換します。
     */
    private function dateTime(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value);
        } catch (Throwable) {
            return null;
        }
    }
}
