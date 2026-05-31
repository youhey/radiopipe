<?php

namespace App\Topics\Ratings;

/**
 * digestpipe Article Rating API から返された rating 状態。
 */
class DigestpipeRatingResult
{
    /** @var int digestpipe article id */
    public int $articleId;

    /** @var int|null upstream rating 値 */
    public ?int $rating;

    /** @var string|null rating 更新日時 */
    public ?string $ratedAt;

    /**
     * Constructor.
     */
    public function __construct(int $articleId, ?int $rating, ?string $ratedAt)
    {
        $this->articleId = $articleId;
        $this->rating = $rating;
        $this->ratedAt = $ratedAt;
    }
}
