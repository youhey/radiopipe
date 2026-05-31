<?php

namespace App\Topics\Ratings;

/**
 * radiopipe Topic Rating API の response 用 rating 状態。
 */
class TopicRatingResult
{
    /** @var string radiopipe topic id */
    public string $topicId;

    /** @var string upstream provider 名 */
    public string $upstreamProvider;

    /** @var int upstream article id */
    public int $upstreamId;

    /** @var int|null rating 値 */
    public ?int $rating;

    /** @var string|null rating 更新日時 */
    public ?string $ratedAt;

    /**
     * Constructor.
     */
    public function __construct(
        string $topicId,
        string $upstreamProvider,
        int $upstreamId,
        ?int $rating,
        ?string $ratedAt,
    ) {
        $this->topicId = $topicId;
        $this->upstreamProvider = $upstreamProvider;
        $this->upstreamId = $upstreamId;
        $this->rating = $rating;
        $this->ratedAt = $ratedAt;
    }

    /**
     * API response 用配列を返す。
     *
     * @return array{
     *     topic_id: string,
     *     upstream: array{provider: string, id: int},
     *     rating: int|null,
     *     rated_at: string|null
     * }
     */
    public function toArray(): array
    {
        return [
            'topic_id' => $this->topicId,
            'upstream' => [
                'provider' => $this->upstreamProvider,
                'id' => $this->upstreamId,
            ],
            'rating' => $this->rating,
            'rated_at' => $this->ratedAt,
        ];
    }
}
