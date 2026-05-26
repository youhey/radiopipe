<?php

namespace App\Topics\Editorial;

/**
 * 後続 Scenario 選択・執筆向けの Topic Hint
 */
class TopicScenarioNotes
{
    /** @var string|null suggested scenario role */
    public ?string $suggestedRole;

    /** @var string|null suggested tone */
    public ?string $tone;

    /** @var string|null transition hint */
    public ?string $transitionHint;

    /** @var list<string> 避けるべき扱い */
    public array $avoid;

    /**
     * Constructor.
     *
     * @param string|null $suggestedRole
     * @param string|null $tone
     * @param string|null $transitionHint
     * @param list<string> $avoid
     */
    public function __construct(?string $suggestedRole, ?string $tone, ?string $transitionHint, array $avoid)
    {
        $this->suggestedRole = $suggestedRole;
        $this->tone = $tone;
        $this->transitionHint = $transitionHint;
        $this->avoid = $avoid;
    }

    /**
     * JSON 出力向けの連想配列を返す
     *
     * @return array{
     *     suggested_role: string|null,
     *     tone: string|null,
     *     transition_hint: string|null,
     *     avoid: list<string>
     * }
     */
    public function toArray(): array
    {
        return [
            'suggested_role' => $this->suggestedRole,
            'tone' => $this->tone,
            'transition_hint' => $this->transitionHint,
            'avoid' => $this->avoid,
        ];
    }
}
