<?php

namespace App\Upstream;

use Carbon\CarbonImmutable;

/**
 * Upstream Item 取得条件
 */
class UpstreamArticleQuery
{
    /** @var CarbonImmutable|null 取得開始時刻 */
    public ?CarbonImmutable $from;

    /** @var CarbonImmutable|null 取得終了時刻 */
    public ?CarbonImmutable $to;

    /** @var string|null source key */
    public ?string $source;

    /** @var int|null 取得件数 */
    public ?int $limit;

    /**
     * Constructor.
     *
     * @param CarbonImmutable|null $from
     * @param CarbonImmutable|null $to
     * @param string|null $source
     * @param int|null $limit
     */
    public function __construct(
        ?CarbonImmutable $from = null,
        ?CarbonImmutable $to = null,
        ?string $source = null,
        ?int $limit = null,
    ) {
        $this->from = $from;
        $this->to = $to;
        $this->source = $this->blankToNull($source);
        $this->limit = $limit;
    }

    /**
     * API query parameter へ変換して返す
     *
     * @return array{from?: string, to?: string, source?: string, limit?: int}
     */
    public function toQueryParameters(): array
    {
        return array_filter([
            'from' => $this->from?->toJSON(),
            'to' => $this->to?->toJSON(),
            'source' => $this->source,
            'limit' => $this->limit,
        ], static fn (mixed $value): bool => $value !== null && $value !== '');
    }

    /**
     * 空文字列を null 扱いで文字列を返す
     *
     * @param string|null $value
     *
     * @return string|null
     */
    private function blankToNull(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        return trim($value);
    }
}
