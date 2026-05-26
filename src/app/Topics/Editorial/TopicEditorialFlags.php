<?php

namespace App\Topics\Editorial;

/**
 * Topic Editorial Evaluation の判定 Flag 群
 */
class TopicEditorialFlags
{
    /** @var bool semantic duplicate candidate かどうか */
    public bool $isDuplicateCandidate;

    /** @var bool 不確実な topic かどうか */
    public bool $isUncertain;

    /** @var bool sensitive な topic かどうか */
    public bool $isSensitive;

    /**
     * Constructor.
     *
     * @param bool $isDuplicateCandidate
     * @param bool $isUncertain
     * @param bool $isSensitive
     */
    public function __construct(bool $isDuplicateCandidate, bool $isUncertain, bool $isSensitive)
    {
        $this->isDuplicateCandidate = $isDuplicateCandidate;
        $this->isUncertain = $isUncertain;
        $this->isSensitive = $isSensitive;
    }

    /**
     * JSON 出力向けの連想配列を返す
     *
     * @return array{is_duplicate_candidate: bool, is_uncertain: bool, is_sensitive: bool}
     */
    public function toArray(): array
    {
        return [
            'is_duplicate_candidate' => $this->isDuplicateCandidate,
            'is_uncertain' => $this->isUncertain,
            'is_sensitive' => $this->isSensitive,
        ];
    }
}
