<?php

namespace App\Cloud;

/**
 * Laravel Cloud の最新 deployment 表示状態。
 */
class LaravelCloudDeploymentStatus
{
    /** @var bool API 設定が揃っているか */
    public bool $configured;

    /** @var bool deployment 情報を表示できるか */
    public bool $available;

    /** @var string 表示用 deployment status */
    public string $status;

    /** @var string|null deployment ID */
    public ?string $deploymentId;

    /** @var string|null deploy branch */
    public ?string $branch;

    /** @var string|null commit hash */
    public ?string $commitHash;

    /** @var string|null commit message */
    public ?string $commitMessage;

    /** @var string|null commit author */
    public ?string $commitAuthor;

    /** @var string|null deployment started timestamp */
    public ?string $startedAt;

    /** @var string|null deployment finished timestamp */
    public ?string $finishedAt;

    /** @var string|null deployment failure reason */
    public ?string $failureReason;

    /** @var string|null safe error message */
    public ?string $errorMessage;

    /**
     * Constructor.
     */
    public function __construct(
        bool $configured,
        bool $available,
        string $status,
        ?string $deploymentId = null,
        ?string $branch = null,
        ?string $commitHash = null,
        ?string $commitMessage = null,
        ?string $commitAuthor = null,
        ?string $startedAt = null,
        ?string $finishedAt = null,
        ?string $failureReason = null,
        ?string $errorMessage = null,
    ) {
        $this->configured = $configured;
        $this->available = $available;
        $this->status = $status;
        $this->deploymentId = $deploymentId;
        $this->branch = $branch;
        $this->commitHash = $commitHash;
        $this->commitMessage = $commitMessage;
        $this->commitAuthor = $commitAuthor;
        $this->startedAt = $startedAt;
        $this->finishedAt = $finishedAt;
        $this->failureReason = $failureReason;
        $this->errorMessage = $errorMessage;
    }
}
