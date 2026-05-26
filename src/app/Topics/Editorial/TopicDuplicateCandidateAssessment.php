<?php

namespace App\Topics\Editorial;

use InvalidArgumentException;

/**
 * deterministic duplicate candidate 判定の pairwise 結果です。
 */
class TopicDuplicateCandidateAssessment
{
    /** @var int 0-100 duplicate candidate score */
    public int $duplicateScore;

    /** @var array<string, bool|int|string|null> 判定 signal */
    public array $signals;

    /** @var string|null debug/explanation reason */
    public ?string $reason;

    /**
     * Constructor.
     *
     * @param int $duplicateScore
     * @param array<string, bool|int|string|null> $signals
     * @param string|null $reason
     */
    public function __construct(int $duplicateScore, array $signals, ?string $reason)
    {
        if ($duplicateScore < 0 || $duplicateScore > 100) {
            throw new InvalidArgumentException('duplicate_score must be between 0 and 100.');
        }

        $this->duplicateScore = $duplicateScore;
        $this->signals = $signals;
        $this->reason = $reason;
    }

    /**
     * Candidate Threshold 以上ならば TRUE を返す
     *
     * @param int $threshold
     *
     * @return bool
     */
    public function isCandidate(int $threshold = TopicDuplicateCandidateDetector::CANDIDATE_SCORE): bool
    {
        return $this->duplicateScore >= $threshold;
    }

    /**
     * Strong Duplicate Threshold 以上ならば TRUE を返す
     *
     * @param int $threshold
     *
     * @return bool
     */
    public function isStrongDuplicate(int $threshold = TopicDuplicateCandidateDetector::STRONG_DUPLICATE_SCORE): bool
    {
        return $this->duplicateScore >= $threshold;
    }

    /**
     * JSON 出力向けの連想配列を返す
     *
     * @return array{duplicate_score: int, signals: array<string, bool|int|string|null>, reason: string|null}
     */
    public function toArray(): array
    {
        return [
            'duplicate_score' => $this->duplicateScore,
            'signals' => $this->signals,
            'reason' => $this->reason,
        ];
    }
}
