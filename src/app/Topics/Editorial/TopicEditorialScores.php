<?php

namespace App\Topics\Editorial;

use InvalidArgumentException;

/**
 * Topic Editorial Evaluation の score 群
 */
class TopicEditorialScores
{
    /** @var int user preference fit score */
    public int $preference;

    /** @var int general importance score */
    public int $generalImportance;

    /** @var int freshness score */
    public int $freshness;

    /** @var int certainty score */
    public int $certainty;

    /** @var int scenario fitness score */
    public int $scenarioFitness;

    /** @var int flow flexibility score */
    public int $flowFlexibility;

    /**
     * Constructor.
     *
     * @param int $preference
     * @param int $generalImportance
     * @param int $freshness
     * @param int $certainty
     * @param int $scenarioFitness
     * @param int $flowFlexibility
     */
    public function __construct(
        int $preference,
        int $generalImportance,
        int $freshness,
        int $certainty,
        int $scenarioFitness,
        int $flowFlexibility,
    ) {
        $this->preference = $this->assertScore($preference, 'preference');
        $this->generalImportance = $this->assertScore($generalImportance, 'general_importance');
        $this->freshness = $this->assertScore($freshness, 'freshness');
        $this->certainty = $this->assertScore($certainty, 'certainty');
        $this->scenarioFitness = $this->assertScore($scenarioFitness, 'scenario_fitness');
        $this->flowFlexibility = $this->assertScore($flowFlexibility, 'flow_flexibility');
    }

    /**
     * JSON 出力向けの連想配列を返す
     *
     * @return array{
     *     preference: int,
     *     general_importance: int,
     *     freshness: int,
     *     certainty: int,
     *     scenario_fitness: int,
     *     flow_flexibility: int
     * }
     */
    public function toArray(): array
    {
        return [
            'preference' => $this->preference,
            'general_importance' => $this->generalImportance,
            'freshness' => $this->freshness,
            'certainty' => $this->certainty,
            'scenario_fitness' => $this->scenarioFitness,
            'flow_flexibility' => $this->flowFlexibility,
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
