<?php

namespace App\Topics\Editorial;

/**
 * Topic の日本語表示テキストです。
 */
class TopicLocalizedText
{
    /** @var string 日本語タイトル */
    public string $title;

    /** @var string 日本語 summary */
    public string $summary;

    /** @var string 日本語 why-it-matters */
    public string $whyItMatters;

    /**
     * Constructor.
     */
    public function __construct(string $title, string $summary, string $whyItMatters)
    {
        $this->title = $title;
        $this->summary = $summary;
        $this->whyItMatters = $whyItMatters;
    }

    /**
     * JSON 出力向けの配列へ変換します。
     *
     * @return array{title: string, summary: string, why_it_matters: string}
     */
    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'summary' => $this->summary,
            'why_it_matters' => $this->whyItMatters,
        ];
    }
}
