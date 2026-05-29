<?php

namespace App\Http\Resources;

use App\Models\Episode;
use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Episode 一覧 API の軽量 response resource。
 *
 * @mixin Episode
 */
class EpisodeIndexResource extends JsonResource
{
    /**
     * Episode metadata を client 向けに変換する。
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
        ];
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
