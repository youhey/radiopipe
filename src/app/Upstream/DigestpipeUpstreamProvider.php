<?php

namespace App\Upstream;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;

/**
 * digestpipe private API から完了済み digest 記事を取得する provider です。
 */
class DigestpipeUpstreamProvider implements UpstreamProvider
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
     * digestpipe response を内部 upstream 記事形式へ正規化します。
     *
     * @return list<UpstreamArticleItem>
     */
    public function fetch(UpstreamArticleQuery $query): array
    {
        $request = Http::baseUrl($this->baseUrl)
            ->acceptJson()
            ->timeout($this->timeout)
            ->retry($this->maxRetries, 100, null, false);

        if (is_string($this->apiKey) && $this->apiKey !== '') {
            $request = $request->withToken($this->apiKey);
        }

        $response = $request->get('/api/articles', $query->toQueryParameters());

        if ($response->failed()) {
            throw UpstreamProviderException::failedHttpResponse('digestpipe', $response->status());
        }

        $payload = $response->json();

        if (! is_array($payload)) {
            throw UpstreamProviderException::invalidResponse('digestpipe');
        }

        $records = $payload['data'] ?? null;

        if (! is_array($records)) {
            throw UpstreamProviderException::invalidResponse('digestpipe');
        }

        $fetchedAt = CarbonImmutable::now('UTC');
        $items = [];

        foreach ($records as $record) {
            if (! is_array($record)) {
                continue;
            }

            $upstreamId = $record['id'] ?? null;

            if (! is_int($upstreamId) && ! is_string($upstreamId)) {
                continue;
            }

            $items[] = new UpstreamArticleItem(
                upstreamId: $upstreamId,
                source: $this->arrayValue($record['source'] ?? null),
                article: $this->arrayValue($record['article'] ?? null),
                selection: $this->arrayValue($record['selection'] ?? null),
                analysis: $this->arrayValue($record['analysis'] ?? null),
                processing: $this->arrayValue($record['processing'] ?? null),
                fetchedAt: $fetchedAt,
                providerName: 'digestpipe',
            );
        }

        return $items;
    }

    /**
     * provider の object-like 値を配列として扱います。
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
}
