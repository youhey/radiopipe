<?php

namespace App\Topics\Screening;

/**
 * Topic screening keyword rule import の処理件数。
 */
class TopicScreeningKeywordRuleImportResult
{
    private int $createdCount;

    private int $updatedCount;

    private int $skippedCount;

    /**
     * Constructor.
     */
    public function __construct(int $createdCount, int $updatedCount, int $skippedCount)
    {
        $this->createdCount = $createdCount;
        $this->updatedCount = $updatedCount;
        $this->skippedCount = $skippedCount;
    }

    /**
     * 作成された rule 件数を返す。
     */
    public function createdCount(): int
    {
        return $this->createdCount;
    }

    /**
     * 更新された rule 件数を返す。
     */
    public function updatedCount(): int
    {
        return $this->updatedCount;
    }

    /**
     * 空行として skip された件数を返す。
     */
    public function skippedCount(): int
    {
        return $this->skippedCount;
    }
}
