<?php

namespace App\News;

use Carbon\CarbonImmutable;

/**
 * provider response から正規化した一般ニュース項目です。
 */
class NewsItem
{
    /** @var string provider 名 */
    public string $providerName;

    /** @var string|null source 名 */
    public ?string $sourceName;

    /** @var string|null source URL */
    public ?string $sourceUrl;

    /** @var string タイトル */
    public string $title;

    /** @var string URL */
    public string $url;

    /** @var string|null 要約 */
    public ?string $summary;

    /** @var string|null 著者 */
    public ?string $author;

    /** @var string|null カテゴリ */
    public ?string $category;

    /** @var string|null 言語コード */
    public ?string $language;

    /** @var string|null 国コード */
    public ?string $country;

    /** @var CarbonImmutable|null 公開時刻 */
    public ?CarbonImmutable $publishedAt;

    /** @var CarbonImmutable 取得時刻 */
    public CarbonImmutable $fetchedAt;

    /** @var string|null provider attribution または source label */
    public ?string $sourceLabel;

    /**
     * Constructor.
     */
    public function __construct(
        string $providerName,
        ?string $sourceName,
        ?string $sourceUrl,
        string $title,
        string $url,
        ?string $summary,
        ?string $author,
        ?string $category,
        ?string $language,
        ?string $country,
        ?CarbonImmutable $publishedAt,
        CarbonImmutable $fetchedAt,
        ?string $sourceLabel,
    ) {
        $this->providerName = $providerName;
        $this->sourceName = $sourceName;
        $this->sourceUrl = $sourceUrl;
        $this->title = $title;
        $this->url = $url;
        $this->summary = $summary;
        $this->author = $author;
        $this->category = $category;
        $this->language = $language;
        $this->country = $country;
        $this->publishedAt = $publishedAt;
        $this->fetchedAt = $fetchedAt;
        $this->sourceLabel = $sourceLabel;
    }
}
