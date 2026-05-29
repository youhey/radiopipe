<?php

namespace App\Topics\Candidates;

use Carbon\CarbonImmutable;

/**
 * CandidateTopic nomination の実行条件。
 */
class CandidateTopicNominationInput
{
    public CarbonImmutable $from;

    public CarbonImmutable $to;

    public int $limit;

    public CarbonImmutable $processedAt;

    public bool $force;

    /**
     * Constructor.
     */
    public function __construct(CarbonImmutable $from, CarbonImmutable $to, int $limit, CarbonImmutable $processedAt, bool $force)
    {
        $this->from = $from;
        $this->to = $to;
        $this->limit = $limit;
        $this->processedAt = $processedAt;
        $this->force = $force;
    }
}
