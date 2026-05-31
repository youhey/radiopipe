<?php

namespace App\Topics\Ratings;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * digestpipe Article Rating API に rating を転送する client。
 */
class DigestpipeRatingClient
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
     * digestpipe article rating を設定する。
     */
    public function setRating(int $articleId, int $rating): DigestpipeRatingResult
    {
        return $this->send('put', $articleId, ['rating' => $rating]);
    }

    /**
     * digestpipe article rating を解除する。
     */
    public function clearRating(int $articleId): DigestpipeRatingResult
    {
        return $this->send('delete', $articleId, []);
    }

    /**
     * upstream request を送信し、rating response を正規化する。
     *
     * @param array<string, mixed> $payload
     */
    private function send(string $method, int $articleId, array $payload): DigestpipeRatingResult
    {
        if ($this->baseUrl === '' || ! is_string($this->apiKey) || $this->apiKey === '') {
            throw TopicRatingUpstreamException::unavailable();
        }

        $request = Http::baseUrl($this->baseUrl)
            ->withToken($this->apiKey)
            ->acceptJson()
            ->asJson()
            ->timeout($this->timeout)
            ->retry($this->maxRetries, 100, null, false);

        try {
            $response = $method === 'delete'
                ? $request->delete("/api/articles/{$articleId}/rating")
                : $request->put("/api/articles/{$articleId}/rating", $payload);
        } catch (ConnectionException) {
            throw TopicRatingUpstreamException::unavailable();
        }

        if ($response->failed()) {
            throw TopicRatingUpstreamException::failed();
        }

        $responsePayload = $response->json();

        if (! is_array($responsePayload)) {
            throw TopicRatingUpstreamException::invalidResponse();
        }

        $articleRating = $responsePayload['article_rating'] ?? null;

        if (! is_array($articleRating)) {
            throw TopicRatingUpstreamException::invalidResponse();
        }

        $upstreamArticleId = $this->intValue($articleRating['article_id'] ?? null);

        if ($upstreamArticleId === null) {
            throw TopicRatingUpstreamException::invalidResponse();
        }

        return new DigestpipeRatingResult(
            articleId: $upstreamArticleId,
            rating: $this->nullableIntValue($articleRating['rating'] ?? null),
            ratedAt: $this->nullableStringValue($articleRating['rated_at'] ?? null),
        );
    }

    private function intValue(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && ctype_digit($value)) {
            return (int) $value;
        }

        return null;
    }

    private function nullableIntValue(mixed $value): ?int
    {
        if ($value === null) {
            return null;
        }

        return $this->intValue($value);
    }

    private function nullableStringValue(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
