<?php

namespace App\Scenarios;

/**
 * Scenario での topic 採用結果を表す値オブジェクト。
 */
class ScenarioTopicSelection
{
    /** @var string topic id */
    public string $topicId;

    /** @var ScenarioTopicSelectionStatus selection status */
    public ScenarioTopicSelectionStatus $status;

    /** @var int|null selection rank */
    public ?int $rank;

    /** @var string|null selection reason */
    public ?string $reason;

    /** @var array<string, mixed> selection metadata */
    public array $metadata;

    /**
     * Constructor.
     *
     * @param string $topicId
     * @param ScenarioTopicSelectionStatus $status
     * @param int|null $rank
     * @param string|null $reason
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        string $topicId,
        ScenarioTopicSelectionStatus $status,
        ?int $rank,
        ?string $reason,
        array $metadata = [],
    ) {
        $this->topicId = $topicId;
        $this->status = $status;
        $this->rank = $rank;
        $this->reason = $reason;
        $this->metadata = $metadata;
    }

    /**
     * JSON 出力向けの連想配列を返す。
     *
     * @return array{
     *     topic_id: string,
     *     status: string,
     *     rank: int|null,
     *     reason: string|null,
     *     metadata: array<string, mixed>
     * }
     */
    public function toArray(): array
    {
        return [
            'topic_id' => $this->topicId,
            'status' => $this->status->value,
            'rank' => $this->rank,
            'reason' => $this->reason,
            'metadata' => $this->metadata,
        ];
    }
}
