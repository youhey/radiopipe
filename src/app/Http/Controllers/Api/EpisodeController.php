<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\EpisodeDetailResource;
use App\Http\Resources\EpisodeIndexResource;
use App\Models\Episode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * private Episode JSON API の read-only controller。
 */
class EpisodeController extends Controller
{
    /**
     * Episode の軽量一覧を返す。
     */
    public function index(Request $request): JsonResponse
    {
        /** @var array{limit?: int|string, status?: string, character?: string, from?: string, to?: string} $validated */
        $validated = Validator::make($request->query(), [
            'limit' => ['sometimes', 'integer', 'min:1'],
            'status' => ['sometimes', 'string', Rule::in([Episode::STATUS_COMPLETED])],
            'character' => ['sometimes', 'string', 'max:255'],
            'from' => ['sometimes', 'date'],
            'to' => ['sometimes', 'date'],
        ])->validate();

        $limit = min((int) ($validated['limit'] ?? 100), 500);
        $status = $validated['status'] ?? Episode::STATUS_COMPLETED;

        $query = Episode::query()
            ->with('characterProfile')
            ->where('status', $status);

        $query->getQuery()
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->limit($limit);

        if (isset($validated['character'])) {
            $query->where('character_key', $validated['character']);
        }

        if (isset($validated['from'])) {
            $query->where('published_at', '>=', $validated['from']);
        }

        if (isset($validated['to'])) {
            $query->where('published_at', '<=', $validated['to']);
        }

        $episodes = $query->get();

        return response()->json([
            'episodes' => EpisodeIndexResource::collection($episodes)->resolve($request),
            'meta' => [
                'count' => $episodes->count(),
                'limit' => $limit,
            ],
        ]);
    }

    /**
     * 最新の completed Episode を返す。
     */
    public function latest(): EpisodeDetailResource|JsonResponse
    {
        $query = $this->completedEpisodeQuery();
        $query->getQuery()
            ->orderByDesc('published_at')
            ->orderByDesc('id');

        $episode = $query->first();

        if (! $episode instanceof Episode) {
            return response()->json(['message' => 'Episode not found.'], 404);
        }

        return new EpisodeDetailResource($episode);
    }

    /**
     * episode_key で completed Episode を返す。
     */
    public function show(string $episodeKey): EpisodeDetailResource|JsonResponse
    {
        $episode = $this->completedEpisodeQuery()
            ->where('episode_key', $episodeKey)
            ->first();

        if (! $episode instanceof Episode) {
            return response()->json(['message' => 'Episode not found.'], 404);
        }

        return new EpisodeDetailResource($episode);
    }

    /**
     * completed Episode detail 用の共通 query を返す。
     *
     * @return Builder<Episode>
     */
    private function completedEpisodeQuery(): Builder
    {
        return Episode::query()
            ->with([
                'characterProfile',
                'topics' => static function (Relation $query): void {
                    if ($query instanceof HasMany) {
                        $query->getQuery()
                            ->getQuery()
                            ->orderBy('sort_order')
                            ->orderBy('id');
                    }
                },
            ])
            ->where('status', Episode::STATUS_COMPLETED);
    }
}
