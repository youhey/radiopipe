<?php

namespace App\Episodes;

use App\Models\Episode;
use App\Scenarios\ScenarioGenerationResult;
use App\Scenarios\ScenarioTopicSelection;

/**
 * Episode generation pipeline の実行結果。
 */
class EpisodeGenerationRunResult
{
    public ScenarioGenerationResult $scenarioResult;

    /** @var list<ScenarioTopicSelection> */
    public array $topicSelections;

    /** @var list<array<string, mixed>> */
    public array $pipelineItems;

    /** @var list<array<string, mixed>> */
    public array $errors;

    public string $episodeKey;

    public ?Episode $episode;

    /**
     * Constructor.
     *
     * @param list<ScenarioTopicSelection> $topicSelections
     * @param list<array<string, mixed>> $pipelineItems
     * @param list<array<string, mixed>> $errors
     */
    public function __construct(
        ScenarioGenerationResult $scenarioResult,
        array $topicSelections,
        array $pipelineItems,
        array $errors,
        string $episodeKey,
        ?Episode $episode,
    ) {
        $this->scenarioResult = $scenarioResult;
        $this->topicSelections = $topicSelections;
        $this->pipelineItems = $pipelineItems;
        $this->errors = $errors;
        $this->episodeKey = $episodeKey;
        $this->episode = $episode;
    }
}
