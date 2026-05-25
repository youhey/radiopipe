<?php

namespace App\Topics;

/**
 * TopicDraft の deterministic な Stage 1 rule evaluation 結果です。
 */
class TopicRuleEvaluation
{
    /** @var TopicPreStatus pre-evaluation status */
    public TopicPreStatus $preStatus;

    /** @var int 0-100 rule score */
    public int $ruleScore;

    /** @var array<string, bool|float|int|string|null> rule signals */
    public array $signals;

    /** @var array{is_duplicate: bool, is_uncertain: bool, is_sensitive: bool} evaluation flags */
    public array $flags;

    /** @var list<string> debug-friendly reasons */
    public array $reasons;

    /**
     * Constructor.
     *
     * @param array<string, bool|float|int|string|null> $signals
     * @param array{is_duplicate: bool, is_uncertain: bool, is_sensitive: bool} $flags
     * @param list<string> $reasons
     */
    public function __construct(
        TopicPreStatus $preStatus,
        int $ruleScore,
        array $signals,
        array $flags,
        array $reasons,
    ) {
        $this->preStatus = $preStatus;
        $this->ruleScore = $ruleScore;
        $this->signals = $signals;
        $this->flags = $flags;
        $this->reasons = $reasons;
    }

    /**
     * JSON 出力向けの配列へ変換します。
     *
     * @return array{
     *     pre_status: string,
     *     rule_score: int,
     *     signals: array<string, bool|float|int|string|null>,
     *     flags: array{is_duplicate: bool, is_uncertain: bool, is_sensitive: bool},
     *     reasons: list<string>
     * }
     */
    public function toArray(): array
    {
        return [
            'pre_status' => $this->preStatus->value,
            'rule_score' => $this->ruleScore,
            'signals' => $this->signals,
            'flags' => $this->flags,
            'reasons' => $this->reasons,
        ];
    }
}
