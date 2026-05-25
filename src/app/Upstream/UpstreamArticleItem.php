<?php

namespace App\Upstream;

use Carbon\CarbonImmutable;

/**
 * upstream provider response から正規化した記事項目です。
 */
class UpstreamArticleItem
{
    /** @var int|string upstream item id */
    public int|string $upstreamId;

    /** @var array<string, mixed> source metadata */
    public array $source;

    /** @var array<string, mixed> article metadata */
    public array $article;

    /** @var array<string, mixed> selection metadata */
    public array $selection;

    /** @var array<string, mixed> analysis JSON */
    public array $analysis;

    /** @var array<string, mixed> processing metadata */
    public array $processing;

    /** @var CarbonImmutable 取得時刻 */
    public CarbonImmutable $fetchedAt;

    /** @var string provider 名 */
    public string $providerName;

    /**
     * Constructor.
     *
     * @param array<string, mixed> $source
     * @param array<string, mixed> $article
     * @param array<string, mixed> $selection
     * @param array<string, mixed> $analysis
     * @param array<string, mixed> $processing
     */
    public function __construct(
        int|string $upstreamId,
        array $source,
        array $article,
        array $selection,
        array $analysis,
        array $processing,
        CarbonImmutable $fetchedAt,
        string $providerName,
    ) {
        $this->upstreamId = $upstreamId;
        $this->source = $source;
        $this->article = $article;
        $this->selection = $selection;
        $this->analysis = $analysis;
        $this->processing = $processing;
        $this->fetchedAt = $fetchedAt;
        $this->providerName = $providerName;
    }
}
