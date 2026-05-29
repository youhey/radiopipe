<?php

namespace App\Topics\Candidates;

/**
 * CandidateTopic nomination の集計結果。
 */
class CandidateTopicNominationResult
{
    public int $fetched;

    public int $created;

    public int $updated;

    public int $unchanged;

    /** @var list<array<string, mixed>> */
    public array $errors;

    /**
     * Constructor.
     *
     * @param list<array<string, mixed>> $errors
     */
    public function __construct(int $fetched, int $created, int $updated, int $unchanged, array $errors)
    {
        $this->fetched = $fetched;
        $this->created = $created;
        $this->updated = $updated;
        $this->unchanged = $unchanged;
        $this->errors = $errors;
    }
}
