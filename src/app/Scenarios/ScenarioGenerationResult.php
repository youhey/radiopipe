<?php

namespace App\Scenarios;

/**
 * Scenario generation の結果値。
 */
class ScenarioGenerationResult
{
    /** @var Scenario generated scenario */
    public Scenario $scenario;

    /** @var list<ScenarioTopicSelection> topic selection 一覧 */
    public array $topicSelections;

    /** @var array<string, mixed> generation metadata */
    public array $metadata;

    /**
     * Constructor.
     *
     * @param Scenario $scenario
     * @param list<ScenarioTopicSelection> $topicSelections
     * @param array<string, mixed> $metadata
     */
    public function __construct(Scenario $scenario, array $topicSelections = [], array $metadata = [])
    {
        $this->scenario = $scenario;
        $this->topicSelections = $topicSelections;
        $this->metadata = $metadata;
    }

    /**
     * JSON 出力向けの連想配列を返す。
     *
     * @return array{
     *     scenario: array<string, mixed>,
     *     topic_selections: list<array<string, mixed>>,
     *     metadata: array<string, mixed>
     * }
     */
    public function toArray(): array
    {
        return [
            'scenario' => $this->scenario->toArray(),
            'topic_selections' => array_map(
                static fn (ScenarioTopicSelection $selection): array => $selection->toArray(),
                $this->topicSelections,
            ),
            'metadata' => $this->metadata,
        ];
    }
}
