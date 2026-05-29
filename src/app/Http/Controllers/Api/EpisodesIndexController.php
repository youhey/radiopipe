<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

/**
 * private Web API 向けに生成済み Episode 一覧を返す controller。
 */
class EpisodesIndexController extends Controller
{
    /**
     * Episode 一覧を返す。
     */
    public function __invoke(): JsonResponse
    {
        $episodes = DB::table('episodes')
            ->orderByDesc('published_at')
            ->orderByDesc('created_at')
            ->limit(20)
            ->get()
            ->map(fn (object $episode): array => [
                'id' => $episode->id,
                'episode_key' => $episode->episode_key,
                'date' => $episode->date,
                'published_at' => $episode->published_at,
                'processed_at' => $episode->processed_at,
                'status' => $episode->status,
                'title' => $episode->title,
                'language' => $episode->language,
                'character_key' => $episode->character_key,
                'target_duration_seconds' => $episode->target_duration_seconds,
                'estimated_duration_seconds' => $episode->estimated_duration_seconds,
                'topics_count' => $this->topicsCount($this->intValue($episode->id ?? null)),
                'scenario' => $this->jsonValue($episode->scenario_json),
                'metadata' => $this->jsonValue($episode->metadata),
                'errors' => $this->jsonValue($episode->errors),
            ])
            ->all();

        return response()->json([
            'schema_version' => '1.0',
            'data' => $episodes,
        ]);
    }

    /**
     * Episode に紐づく topic 数を返す。
     */
    private function topicsCount(int $episodeId): int
    {
        return DB::table('episode_topics')
            ->where('episode_id', $episodeId)
            ->count();
    }

    /**
     * mixed 値を int に変換する。
     */
    private function intValue(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    /**
     * JSON column 文字列を JSON 互換値に変換する。
     */
    private function jsonValue(mixed $value): mixed
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        $decoded = json_decode($value, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : null;
    }
}
