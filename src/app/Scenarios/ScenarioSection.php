<?php

namespace App\Scenarios;

use InvalidArgumentException;

/**
 * ラジオ風台本を構成する section の値オブジェクト。
 */
class ScenarioSection
{
    /** @var string section type */
    public string $type;

    /** @var string section title */
    public string $title;

    /** @var string 読み上げ本文 */
    public string $text;

    /** @var list<string> section が参照する topic id */
    public array $topicIds;

    /** @var int|null 推定読み上げ秒数 */
    public ?int $estimatedDurationSeconds;

    /** @var array<string, mixed> section metadata */
    public array $metadata;

    /**
     * Constructor.
     *
     * @param string $type
     * @param string $title
     * @param string $text
     * @param list<string> $topicIds
     * @param int|null $estimatedDurationSeconds
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        string $type,
        string $title,
        string $text,
        array $topicIds = [],
        ?int $estimatedDurationSeconds = null,
        array $metadata = [],
    ) {
        $this->type = $type;
        $this->title = $title;
        $this->text = $text;
        $this->topicIds = $topicIds;
        $this->estimatedDurationSeconds = $this->assertOptionalDuration($estimatedDurationSeconds, 'estimated_duration_seconds');
        $this->metadata = $metadata;
    }

    /**
     * JSON 出力向けの連想配列を返す。
     *
     * @return array{
     *     type: string,
     *     title: string,
     *     text: string,
     *     topic_ids: list<string>,
     *     estimated_duration_seconds: int|null,
     *     metadata: array<string, mixed>
     * }
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'title' => $this->title,
            'text' => $this->text,
            'topic_ids' => $this->topicIds,
            'estimated_duration_seconds' => $this->estimatedDurationSeconds,
            'metadata' => $this->metadata,
        ];
    }

    private function assertOptionalDuration(?int $duration, string $field): ?int
    {
        if ($duration !== null && $duration < 0) {
            throw new InvalidArgumentException(sprintf('%s must be a non-negative integer or null.', $field));
        }

        return $duration;
    }
}
