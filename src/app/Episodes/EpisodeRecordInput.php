<?php

namespace App\Episodes;

use App\Models\CharacterProfile;
use App\Scenarios\ScenarioGenerationResult;
use Carbon\CarbonImmutable;

/**
 * EpisodeRecorder に渡す永続化入力。
 */
class EpisodeRecordInput
{
    /** @var ScenarioGenerationResult scenario generation 結果 */
    public ScenarioGenerationResult $result;

    /** @var list<array<string, mixed>> topic pipeline item snapshot 一覧 */
    public array $pipelineItems;

    /** @var CharacterProfile|null 生成時に参照したキャラクター人格 */
    public ?CharacterProfile $characterProfile;

    /** @var string|null 明示 episode key */
    public ?string $episodeKey;

    /** @var CarbonImmutable episode date */
    public CarbonImmutable $date;

    /** @var CarbonImmutable|null published timestamp */
    public ?CarbonImmutable $publishedAt;

    /** @var CarbonImmutable processed timestamp */
    public CarbonImmutable $processedAt;

    /** @var array<string, mixed> safe run metadata */
    public array $metadata;

    /** @var list<array<string, mixed>> safe error summaries */
    public array $errors;

    /**
     * Constructor.
     *
     * @param list<array<string, mixed>> $pipelineItems
     * @param array<string, mixed> $metadata
     * @param list<array<string, mixed>> $errors
     */
    public function __construct(
        ScenarioGenerationResult $result,
        array $pipelineItems = [],
        ?CharacterProfile $characterProfile = null,
        ?string $episodeKey = null,
        ?CarbonImmutable $date = null,
        ?CarbonImmutable $publishedAt = null,
        ?CarbonImmutable $processedAt = null,
        array $metadata = [],
        array $errors = [],
    ) {
        $this->result = $result;
        $this->pipelineItems = $pipelineItems;
        $this->characterProfile = $characterProfile;
        $this->episodeKey = $episodeKey;
        $this->processedAt = $processedAt ?? CarbonImmutable::now();
        $this->date = $date ?? $this->processedAt;
        $this->publishedAt = $publishedAt;
        $this->metadata = $metadata;
        $this->errors = $errors;
    }
}
