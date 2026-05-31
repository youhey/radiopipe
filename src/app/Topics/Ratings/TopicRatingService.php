<?php

namespace App\Topics\Ratings;

use App\Models\CandidateTopic;
use App\Models\EpisodeTopic;

/**
 * local topic を digestpipe article rating に解決して転送する service。
 */
class TopicRatingService
{
    private DigestpipeRatingClient $digestpipe;

    /**
     * Constructor.
     */
    public function __construct(DigestpipeRatingClient $digestpipe)
    {
        $this->digestpipe = $digestpipe;
    }

    /**
     * topic rating を upstream digestpipe に設定する。
     */
    public function setRating(string $topicId, int $rating): TopicRatingResult
    {
        $topic = $this->resolveTopic($topicId);
        $upstream = $this->digestpipe->setRating($topic->upstreamId, $rating);

        return $this->result($topic, $upstream);
    }

    /**
     * topic rating を upstream digestpipe で解除する。
     */
    public function clearRating(string $topicId): TopicRatingResult
    {
        $topic = $this->resolveTopic($topicId);
        $upstream = $this->digestpipe->clearRating($topic->upstreamId);

        return $this->result($topic, $upstream);
    }

    /**
     * public topic id から rating 転送先を解決する。
     */
    private function resolveTopic(string $topicId): RateableTopicRef
    {
        $candidate = CandidateTopic::query()
            ->where('topic_id', $topicId)
            ->first();

        if ($candidate instanceof CandidateTopic) {
            return $this->refFromValues($candidate->topic_id, $candidate->upstream_provider, $candidate->upstream_id);
        }

        $episodeTopic = EpisodeTopic::query()
            ->where('topic_id', $topicId)
            ->get()
            ->sortByDesc('id')
            ->first();

        if ($episodeTopic instanceof EpisodeTopic) {
            return $this->refFromValues($episodeTopic->topic_id, $episodeTopic->upstream_provider, $episodeTopic->upstream_id);
        }

        throw new TopicRatingNotFoundException('Topic was not found.');
    }

    private function refFromValues(mixed $topicId, mixed $provider, mixed $upstreamId): RateableTopicRef
    {
        if (! is_string($topicId) || $topicId === '') {
            throw new TopicRatingNotFoundException('Topic was not found.');
        }

        if (! is_string($provider) || strtolower($provider) !== 'digestpipe') {
            throw new TopicRatingNotFoundException('Topic was not rateable.');
        }

        $articleId = $this->positiveInt($upstreamId);

        if ($articleId === null) {
            throw new TopicRatingNotFoundException('Topic was not rateable.');
        }

        return new RateableTopicRef(
            topicId: $topicId,
            upstreamProvider: 'digestpipe',
            upstreamId: $articleId,
        );
    }

    private function positiveInt(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value > 0 ? $value : null;
        }

        if (is_string($value) && ctype_digit($value)) {
            $intValue = (int) $value;

            return $intValue > 0 ? $intValue : null;
        }

        return null;
    }

    private function result(RateableTopicRef $topic, DigestpipeRatingResult $upstream): TopicRatingResult
    {
        return new TopicRatingResult(
            topicId: $topic->topicId,
            upstreamProvider: $topic->upstreamProvider,
            upstreamId: $upstream->articleId,
            rating: $upstream->rating,
            ratedAt: $upstream->ratedAt,
        );
    }
}
