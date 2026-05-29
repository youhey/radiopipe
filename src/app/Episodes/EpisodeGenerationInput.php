<?php

namespace App\Episodes;

use App\Models\CharacterProfile;
use Carbon\CarbonImmutable;

/**
 * Episode generation pipeline に渡す実行条件。
 */
class EpisodeGenerationInput
{
    public CarbonImmutable $from;

    public CarbonImmutable $to;

    public int $limit;

    public CarbonImmutable $publishedAt;

    public CarbonImmutable $processedAt;

    public CharacterProfile $characterProfile;

    public bool $persist;

    public string $commandName;

    /**
     * Constructor.
     */
    public function __construct(
        CarbonImmutable $from,
        CarbonImmutable $to,
        int $limit,
        CarbonImmutable $publishedAt,
        CarbonImmutable $processedAt,
        CharacterProfile $characterProfile,
        bool $persist,
        string $commandName,
    ) {
        $this->from = $from;
        $this->to = $to;
        $this->limit = $limit;
        $this->publishedAt = $publishedAt;
        $this->processedAt = $processedAt;
        $this->characterProfile = $characterProfile;
        $this->persist = $persist;
        $this->commandName = $commandName;
    }
}
