<?php

namespace App\Scenarios;

use App\Topics\Editorial\TopicEditorialEvaluation;
use InvalidArgumentException;

/**
 * Scenario generation に渡す入力値。
 */
class ScenarioGenerationInput
{
    /** @var string|null character profile key */
    public ?string $characterKey;

    /** @var int 目標読み上げ秒数 */
    public int $targetDurationSeconds;

    /** @var string|null scenario title */
    public ?string $title;

    /** @var string scenario language */
    public string $language;

    /** @var list<TopicEditorialEvaluation> editorial evaluation 一覧 */
    public array $editorialEvaluations;

    /**
     * Constructor.
     *
     * @param string|null $characterKey
     * @param int $targetDurationSeconds
     * @param string|null $title
     * @param string $language
     * @param list<TopicEditorialEvaluation> $editorialEvaluations
     */
    public function __construct(
        ?string $characterKey,
        int $targetDurationSeconds,
        ?string $title,
        string $language,
        array $editorialEvaluations,
    ) {
        if ($targetDurationSeconds < 0) {
            throw new InvalidArgumentException('target_duration_seconds must be a non-negative integer.');
        }

        $this->characterKey = $characterKey;
        $this->targetDurationSeconds = $targetDurationSeconds;
        $this->title = $title;
        $this->language = $language;
        $this->editorialEvaluations = $editorialEvaluations;
    }
}
