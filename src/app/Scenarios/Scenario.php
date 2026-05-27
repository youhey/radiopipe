<?php

namespace App\Scenarios;

use InvalidArgumentException;

/**
 * 生成されたラジオ風台本の値オブジェクト。
 */
class Scenario
{
    /** @var string scenario title */
    public string $title;

    /** @var string scenario language */
    public string $language;

    /** @var int 目標読み上げ秒数 */
    public int $targetDurationSeconds;

    /** @var int|null 推定読み上げ秒数 */
    public ?int $estimatedDurationSeconds;

    /** @var string|null character profile key */
    public ?string $characterKey;

    /** @var string 台本文 */
    public string $scriptText;

    /** @var list<ScenarioSection> section 一覧 */
    public array $sections;

    /** @var array<string, mixed> scenario metadata */
    public array $metadata;

    /**
     * Constructor.
     *
     * @param string $title
     * @param string $language
     * @param int $targetDurationSeconds
     * @param int|null $estimatedDurationSeconds
     * @param string|null $characterKey
     * @param string $scriptText
     * @param list<ScenarioSection> $sections
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        string $title,
        string $language,
        int $targetDurationSeconds,
        ?int $estimatedDurationSeconds,
        ?string $characterKey,
        string $scriptText,
        array $sections = [],
        array $metadata = [],
    ) {
        $this->title = $title;
        $this->language = $language;
        $this->targetDurationSeconds = $this->assertDuration($targetDurationSeconds, 'target_duration_seconds');
        $this->estimatedDurationSeconds = $this->assertOptionalDuration($estimatedDurationSeconds, 'estimated_duration_seconds');
        $this->characterKey = $characterKey;
        $this->scriptText = $scriptText;
        $this->sections = $sections;
        $this->metadata = $metadata;
    }

    /**
     * JSON 出力向けの連想配列を返す。
     *
     * @return array{
     *     title: string,
     *     language: string,
     *     target_duration_seconds: int,
     *     estimated_duration_seconds: int|null,
     *     character_key: string|null,
     *     script_text: string,
     *     sections: list<array<string, mixed>>,
     *     metadata: array<string, mixed>
     * }
     */
    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'language' => $this->language,
            'target_duration_seconds' => $this->targetDurationSeconds,
            'estimated_duration_seconds' => $this->estimatedDurationSeconds,
            'character_key' => $this->characterKey,
            'script_text' => $this->scriptText,
            'sections' => array_map(
                static fn (ScenarioSection $section): array => $section->toArray(),
                $this->sections,
            ),
            'metadata' => $this->metadata,
        ];
    }

    private function assertDuration(int $duration, string $field): int
    {
        if ($duration < 0) {
            throw new InvalidArgumentException(sprintf('%s must be a non-negative integer.', $field));
        }

        return $duration;
    }

    private function assertOptionalDuration(?int $duration, string $field): ?int
    {
        if ($duration !== null && $duration < 0) {
            throw new InvalidArgumentException(sprintf('%s must be a non-negative integer or null.', $field));
        }

        return $duration;
    }
}
