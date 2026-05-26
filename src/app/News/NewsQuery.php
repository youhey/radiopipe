<?php

namespace App\News;

/**
 * 一般ニュース取得条件です。
 */
class NewsQuery
{
    /** @var string|null 検索語 */
    public ?string $query;

    /** @var string|null 国コード */
    public ?string $country;

    /** @var string|null カテゴリ */
    public ?string $category;

    /** @var string|null 言語コード */
    public ?string $language;

    /** @var int|null 取得件数 */
    public ?int $pageSize;

    /** @var string|null provider 固有の source 指定 */
    public ?string $sources;

    /** @var list<string> RSS feed URL */
    public array $feedUrls;

    /**
     * Constructor.
     *
     * @param list<string> $feedUrls
     */
    public function __construct(
        ?string $query = null,
        ?string $country = null,
        ?string $category = null,
        ?string $language = null,
        ?int $pageSize = null,
        ?string $sources = null,
        array $feedUrls = [],
    ) {
        $this->query = $this->blankToNull($query);
        $this->country = $this->blankToNull($country);
        $this->category = $this->blankToNull($category);
        $this->language = $this->blankToNull($language);
        $this->pageSize = $pageSize;
        $this->sources = $this->blankToNull($sources);
        $this->feedUrls = array_values(array_filter(
            $feedUrls,
            static fn (string $feedUrl): bool => $feedUrl !== '',
        ));
    }

    /**
     * 空文字列は null 扱いで文字列を返す
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
