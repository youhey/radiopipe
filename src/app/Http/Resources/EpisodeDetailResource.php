<?php

namespace App\Http\Resources;

use App\Models\Episode;
use App\Models\EpisodeTopic;
use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use stdClass;

/**
 * Episode 詳細 API の client-safe response resource。
 *
 * @mixin Episode
 */
class EpisodeDetailResource extends JsonResource
{
    /**
     * Episode detail を client 向けに変換する。
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'episode_key' => $this->episode_key,
            'status' => $this->status,
            'published_at' => $this->dateValue($this->published_at),
            'processed_at' => $this->dateValue($this->processed_at),
            'title' => $this->title,
            'character' => [
                'key' => $this->character_key,
                'name' => $this->characterProfile?->name,
            ],
            'language' => $this->language,
            'scenario' => $this->scenario_json ?? new stdClass(),
            'topics' => $this->topics
                ->map(fn (EpisodeTopic $topic): array => $this->topicPayload($topic))
                ->values()
                ->all(),
        ];
    }

    /**
     * EpisodeTopic snapshot から表示用 topic payload を作る。
     *
     * @return array<string, mixed>
     */
    private function topicPayload(EpisodeTopic $topic): array
    {
        return [
            'topic_id' => $topic->topic_id,
            'status' => $topic->scenario_selection_status,
            'title' => $this->firstString([
                $topic->title,
                data_get($topic->editorial_json, 'localized.title'),
                data_get($topic->topic_draft_json, 'title'),
            ]),
            'summary' => $this->firstString([
                data_get($topic->editorial_json, 'localized.summary'),
                data_get($topic->topic_draft_json, 'summary_seed'),
            ]),
            'why_it_matters' => $this->firstString([
                data_get($topic->editorial_json, 'localized.why_it_matters'),
                data_get($topic->topic_draft_json, 'why_it_matters_seed'),
            ]),
            'source_name' => $this->firstString([
                $topic->source_name,
                data_get($topic->topic_draft_json, 'source_name'),
            ]),
            'url' => $this->firstString([
                $topic->url,
                data_get($topic->topic_draft_json, 'url'),
            ]),
            'discussion_url' => $this->firstString([
                data_get($topic->topic_draft_json, 'discussion_url'),
            ]),
            'sort_order' => $topic->sort_order,
        ];
    }

    /**
     * 最初の空でない文字列を返す。
     *
     * @param array<int, mixed> $values
     */
    private function firstString(array $values): ?string
    {
        foreach ($values as $value) {
            if (is_string($value) && trim($value) !== '') {
                return $value;
            }
        }

        return null;
    }

    /**
     * 日時値を ISO 8601 文字列へ変換する。
     */
    private function dateValue(mixed $value): ?string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('c');
        }

        return is_string($value) && $value !== '' ? $value : null;
    }
}
