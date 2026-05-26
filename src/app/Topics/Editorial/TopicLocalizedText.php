<?php

namespace App\Topics\Editorial;

/**
 * Topic の日本語表示テキスト
 */
class TopicLocalizedText
{
    /** @var string 日本語タイトル */
    public string $title;

    /** @var string 日本語要約文 */
    public string $summary;

    /** @var string 日本語 why-it-matters */
    public string $whyItMatters;

    /**
     * Constructor.
     *
     * @param string $title
     * @param string $summary
     * @param string $whyItMatters
     */
    public function __construct(string $title, string $summary, string $whyItMatters)
    {
        $this->title = $title;
        $this->summary = $summary;
        $this->whyItMatters = $whyItMatters;
    }

    /**
     * JSON 出力向けの連想配列を返す
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
