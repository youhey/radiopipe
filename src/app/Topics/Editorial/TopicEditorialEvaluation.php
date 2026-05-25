<?php

namespace App\Topics\Editorial;

use InvalidArgumentException;

/**
 * Topic Editorial Evaluation の構造化結果です。
 */
class TopicEditorialEvaluation
{
    /** @var TopicEditorialStatus editorial phase status */
    public TopicEditorialStatus $status;

    /** @var int 0-100 editorial aggregate score */
    public int $editorialScore;

    /** @var TopicLocalizedText localized topic text */
    public TopicLocalizedText $localized;

    /** @var TopicEditorialScores editorial score details */
    public TopicEditorialScores $scores;

    /** @var TopicEditorialFlags editorial flags */
    public TopicEditorialFlags $flags;

    /** @var TopicDuplicateAssessment duplicate assessment */
    public TopicDuplicateAssessment $duplicate;

    /** @var TopicScenarioNotes scenario hints */
    public TopicScenarioNotes $scenarioNotes;

    /** @var list<string> debug/explanation reasons */
    public array $reasons;

    /** @var array<string, mixed> implementation metadata */
    public array $metadata;

    /**
     * Constructor.
     *
     * @param list<string> $reasons
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        TopicEditorialStatus $status,
        int $editorialScore,
        TopicLocalizedText $localized,
        TopicEditorialScores $scores,
        TopicEditorialFlags $flags,
        TopicDuplicateAssessment $duplicate,
        TopicScenarioNotes $scenarioNotes,
        array $reasons = [],
        array $metadata = [],
    ) {
        $this->status = $status;
        $this->editorialScore = $this->assertScore($editorialScore, 'editorial_score');
        $this->localized = $localized;
        $this->scores = $scores;
        $this->flags = $flags;
        $this->duplicate = $duplicate;
        $this->scenarioNotes = $scenarioNotes;
        $this->reasons = $reasons;
        $this->metadata = $metadata;
    }

    /**
     * JSON 出力向けの配列へ変換します。
     *
     * @return array{
     *     status: string,
     *     editorial_score: int,
     *     localized: array{title: string, summary: string, why_it_matters: string},
     *     scores: array{preference: int, general_importance: int, freshness: int, certainty: int, scenario_fitness: int, flow_flexibility: int},
     *     flags: array{is_duplicate_candidate: bool, is_uncertain: bool, is_sensitive: bool},
     *     duplicate: array{canonical_key: string|null, similar_topic_ids: list<string>, duplicate_of: string|null, confidence: int|null, reason: string|null},
     *     scenario_notes: array{suggested_role: string|null, tone: string|null, transition_hint: string|null, avoid: list<string>},
     *     reasons: list<string>,
     *     metadata: array<string, mixed>
     * }
     */
    public function toArray(): array
    {
        return [
            'status' => $this->status->value,
            'editorial_score' => $this->editorialScore,
            'localized' => $this->localized->toArray(),
            'scores' => $this->scores->toArray(),
            'flags' => $this->flags->toArray(),
            'duplicate' => $this->duplicate->toArray(),
            'scenario_notes' => $this->scenarioNotes->toArray(),
            'reasons' => $this->reasons,
            'metadata' => $this->metadata,
        ];
    }

    private function assertScore(int $score, string $field): int
    {
        if ($score < 0 || $score > 100) {
            throw new InvalidArgumentException(sprintf('%s must be between 0 and 100.', $field));
        }

        return $score;
    }
}
