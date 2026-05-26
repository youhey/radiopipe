<?php

namespace App\Topics\Editorial;

use InvalidArgumentException;

/**
 * Topic の Semantic Duplicate 候補情報
 */
class TopicDuplicateAssessment
{
    /** @var string|null duplicate grouping key */
    public ?string $canonicalKey;

    /** @var list<string> 類似 topic id */
    public array $similarTopicIds;

    /** @var string|null duplicate 元 topic id */
    public ?string $duplicateOf;

    /** @var int|null duplicate assessment confidence */
    public ?int $confidence;

    /** @var string|null debug/explanation reason */
    public ?string $reason;

    /**
     * Constructor.
     *
     * @param string|null $canonicalKey
     * @param list<string> $similarTopicIds
     * @param string|null $duplicateOf
     * @param int|null $confidence
     * @param string|null $reason
     */
    public function __construct(
        ?string $canonicalKey,
        array $similarTopicIds,
        ?string $duplicateOf,
        ?int $confidence,
        ?string $reason,
    ) {
        $this->canonicalKey = $canonicalKey;
        $this->similarTopicIds = $similarTopicIds;
        $this->duplicateOf = $duplicateOf;
        $this->confidence = $this->assertOptionalScore($confidence, 'confidence');
        $this->reason = $reason;
    }

    /**
     * JSON 出力向けの連想配列を返す
     *
     * @return array{
     *     canonical_key: string|null,
     *     similar_topic_ids: list<string>,
     *     duplicate_of: string|null,
     *     confidence: int|null,
     *     reason: string|null
     * }
     */
    public function toArray(): array
    {
        return [
            'canonical_key' => $this->canonicalKey,
            'similar_topic_ids' => $this->similarTopicIds,
            'duplicate_of' => $this->duplicateOf,
            'confidence' => $this->confidence,
            'reason' => $this->reason,
        ];
    }

    private function assertOptionalScore(?int $score, string $field): ?int
    {
        if ($score !== null && ($score < 0 || $score > 100)) {
            throw new InvalidArgumentException(sprintf('%s must be null or between 0 and 100.', $field));
        }

        return $score;
    }
}
