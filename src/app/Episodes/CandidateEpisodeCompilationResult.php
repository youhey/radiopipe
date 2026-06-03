<?php

namespace App\Episodes;

use App\Models\CandidateTopic;
use App\Models\Episode;
use App\Scenarios\ScenarioGenerationResult;
use App\Scenarios\ScenarioTopicSelection;

/**
 * CandidateTopic からの Episode compilation 結果。
 */
class CandidateEpisodeCompilationResult
{
    public ?ScenarioGenerationResult $scenarioResult;

    /** @var list<ScenarioTopicSelection> */
    public array $topicSelections;

    /** @var list<CandidateTopic> */
    public array $candidateTopics;

    /** @var list<array<string, mixed>> */
    public array $pipelineItems;

    public string $compileFingerprint;

    public bool $skipped;

    public ?Episode $episode;

    public ?string $skipReason;

    public ?int $requiredCandidateTopicCount;

    public ?int $actualCandidateTopicCount;

    /**
     * Constructor.
     *
     * @param list<ScenarioTopicSelection> $topicSelections
     * @param list<CandidateTopic> $candidateTopics
     * @param list<array<string, mixed>> $pipelineItems
     */
    public function __construct(
        ?ScenarioGenerationResult $scenarioResult,
        array $topicSelections,
        array $candidateTopics,
        array $pipelineItems,
        string $compileFingerprint,
        bool $skipped,
        ?Episode $episode,
        ?string $skipReason = null,
        ?int $requiredCandidateTopicCount = null,
        ?int $actualCandidateTopicCount = null,
    ) {
        $this->scenarioResult = $scenarioResult;
        $this->topicSelections = $topicSelections;
        $this->candidateTopics = $candidateTopics;
        $this->pipelineItems = $pipelineItems;
        $this->compileFingerprint = $compileFingerprint;
        $this->skipped = $skipped;
        $this->episode = $episode;
        $this->skipReason = $skipReason;
        $this->requiredCandidateTopicCount = $requiredCandidateTopicCount;
        $this->actualCandidateTopicCount = $actualCandidateTopicCount;
    }
}
