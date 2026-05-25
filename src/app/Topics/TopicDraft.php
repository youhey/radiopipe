<?php

namespace App\Topics;

use Carbon\CarbonImmutable;

/**
 * upstream 記事から作る radiopipe 内部 topic の初期表現です。
 */
class TopicDraft
{
    /** @var string draft id */
    public string $id;

    /** @var string source 種別 */
    public string $sourceType;

    /** @var string|null source 名 */
    public ?string $sourceName;

    /** @var string|null 表示用タイトル */
    public ?string $title;

    /** @var string|null 元タイトル */
    public ?string $originalTitle;

    /** @var string|null 記事 URL */
    public ?string $url;

    /** @var string|null discussion URL */
    public ?string $discussionUrl;

    /** @var string|null 後続 summary 生成の種になる短い説明 */
    public ?string $summarySeed;

    /** @var string|null 後続 why-it-matters 生成の種になる説明 */
    public ?string $whyItMattersSeed;

    /** @var list<string> topic tags */
    public array $tags;

    /** @var list<string> entity names */
    public array $entities;

    /** @var int|float|null upstream importance */
    public float|int|null $importance;

    /** @var float|null upstream confidence */
    public ?float $confidence;

    /** @var string|null upstream content type */
    public ?string $contentType;

    /** @var string|null upstream limitations */
    public ?string $limitations;

    /** @var CarbonImmutable|null 公開時刻 */
    public ?CarbonImmutable $publishedAt;

    /** @var CarbonImmutable|null upstream 取得または処理時刻 */
    public ?CarbonImmutable $fetchedAt;

    /** @var array{provider: string, upstream_id: int|string} source reference */
    public array $sourceRefs;

    /** @var array{status?: string|null, score?: int|null} upstream selection metadata */
    public array $upstreamSelection;

    /**
     * Constructor.
     *
     * @param list<string> $tags
     * @param list<string> $entities
     * @param array{provider: string, upstream_id: int|string} $sourceRefs
     * @param array{status?: string|null, score?: int|null} $upstreamSelection
     */
    public function __construct(
        string $id,
        string $sourceType,
        ?string $sourceName,
        ?string $title,
        ?string $originalTitle,
        ?string $url,
        ?string $discussionUrl,
        ?string $summarySeed,
        ?string $whyItMattersSeed,
        array $tags,
        array $entities,
        float|int|null $importance,
        ?float $confidence,
        ?string $contentType,
        ?string $limitations,
        ?CarbonImmutable $publishedAt,
        ?CarbonImmutable $fetchedAt,
        array $sourceRefs,
        array $upstreamSelection = [],
    ) {
        $this->id = $id;
        $this->sourceType = $sourceType;
        $this->sourceName = $sourceName;
        $this->title = $title;
        $this->originalTitle = $originalTitle;
        $this->url = $url;
        $this->discussionUrl = $discussionUrl;
        $this->summarySeed = $summarySeed;
        $this->whyItMattersSeed = $whyItMattersSeed;
        $this->tags = $tags;
        $this->entities = $entities;
        $this->importance = $importance;
        $this->confidence = $confidence;
        $this->contentType = $contentType;
        $this->limitations = $limitations;
        $this->publishedAt = $publishedAt;
        $this->fetchedAt = $fetchedAt;
        $this->sourceRefs = $sourceRefs;
        $this->upstreamSelection = $upstreamSelection;
    }

    /**
     * JSON 出力向けの配列へ変換します。
     *
     * @return array{
     *     id: string,
     *     source_type: string,
     *     source_name: string|null,
     *     title: string|null,
     *     original_title: string|null,
     *     url: string|null,
     *     discussion_url: string|null,
     *     summary_seed: string|null,
     *     why_it_matters_seed: string|null,
     *     tags: list<string>,
     *     entities: list<string>,
     *     importance: int|float|null,
     *     confidence: float|null,
     *     content_type: string|null,
     *     limitations: string|null,
     *     published_at: string|null,
     *     fetched_at: string|null,
     *     source_refs: array{provider: string, upstream_id: int|string},
     *     upstream_selection: array{status?: string|null, score?: int|null}
     * }
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'source_type' => $this->sourceType,
            'source_name' => $this->sourceName,
            'title' => $this->title,
            'original_title' => $this->originalTitle,
            'url' => $this->url,
            'discussion_url' => $this->discussionUrl,
            'summary_seed' => $this->summarySeed,
            'why_it_matters_seed' => $this->whyItMattersSeed,
            'tags' => $this->tags,
            'entities' => $this->entities,
            'importance' => $this->importance,
            'confidence' => $this->confidence,
            'content_type' => $this->contentType,
            'limitations' => $this->limitations,
            'published_at' => $this->publishedAt?->toJSON(),
            'fetched_at' => $this->fetchedAt?->toJSON(),
            'source_refs' => $this->sourceRefs,
            'upstream_selection' => $this->upstreamSelection,
        ];
    }
}
