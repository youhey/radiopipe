<?php

namespace App\Topics\Ratings;

/**
 * digestpipe rating へ転送できる local topic 参照。
 */
class RateableTopicRef
{
    /** @var string radiopipe topic id */
    public string $topicId;

    /** @var string upstream provider 名 */
    public string $upstreamProvider;

    /** @var int upstream article id */
    public int $upstreamId;

    /**
     * Constructor.
     */
    public function __construct(string $topicId, string $upstreamProvider, int $upstreamId)
    {
        $this->topicId = $topicId;
        $this->upstreamProvider = $upstreamProvider;
        $this->upstreamId = $upstreamId;
    }
}
