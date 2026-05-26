<?php

namespace App\Topics\Screening;

/**
 * TopicDraft の deterministic な Stage 1 screening 結果
 */
class TopicScreeningEvaluation
{
    /** @var TopicScreeningStatus screening status */
    public TopicScreeningStatus $screeningStatus;

    /** @var int 0-100 screening score */
    public int $screeningScore;

    /** @var array<string, bool|float|int|string|null> screening signals */
    public array $signals;

    /** @var array{is_duplicate: bool, is_uncertain: bool, is_sensitive: bool} screening flags */
    public array $flags;

    /** @var list<string> debug-friendly reasons */
    public array $reasons;

    /**
     * Constructor.
     *
     * @param TopicScreeningStatus $screeningStatus
     * @param int $screeningScore
     * @param array<string, bool|float|int|string|null> $signals
     * @param array{is_duplicate: bool, is_uncertain: bool, is_sensitive: bool} $flags
     * @param list<string> $reasons
     */
    public function __construct(
        TopicScreeningStatus $screeningStatus,
        int $screeningScore,
        array $signals,
        array $flags,
        array $reasons,
    ) {
        $this->screeningStatus = $screeningStatus;
        $this->screeningScore = $screeningScore;
        $this->signals = $signals;
        $this->flags = $flags;
        $this->reasons = $reasons;
    }

    /**
     * JSON 出力向けの連想配列を返す
     *
     * @return array{
     *     screening_status: string,
     *     screening_score: int,
     *     signals: array<string, bool|float|int|string|null>,
     *     flags: array{is_duplicate: bool, is_uncertain: bool, is_sensitive: bool},
     *     reasons: list<string>
     * }
     */
    public function toArray(): array
    {
        return [
            'screening_status' => $this->screeningStatus->value,
            'screening_score' => $this->screeningScore,
            'signals' => $this->signals,
            'flags' => $this->flags,
            'reasons' => $this->reasons,
        ];
    }
}
