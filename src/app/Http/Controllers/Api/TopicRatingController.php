<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Topics\Ratings\TopicRatingNotFoundException;
use App\Topics\Ratings\TopicRatingService;
use App\Topics\Ratings\TopicRatingUpstreamException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * private Topic Rating API controller。
 */
class TopicRatingController extends Controller
{
    /**
     * topic rating を upstream digestpipe に設定する。
     */
    public function update(Request $request, string $id, TopicRatingService $ratings): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'rating' => ['required', 'integer', Rule::in([-1, 1, 2, 3, 4, 5])],
        ]);
        /** @var array<string, mixed> $data */
        $data = $validator->validate();

        $rating = $this->ratingValue($data);

        try {
            $result = $ratings->setRating($id, $rating);
        } catch (TopicRatingNotFoundException) {
            abort(404);
        } catch (TopicRatingUpstreamException $exception) {
            return $this->upstreamError($exception);
        }

        return response()->json([
            'topic_rating' => $result->toArray(),
        ]);
    }

    /**
     * topic rating を upstream digestpipe で解除する。
     */
    public function destroy(string $id, TopicRatingService $ratings): JsonResponse
    {
        try {
            $result = $ratings->clearRating($id);
        } catch (TopicRatingNotFoundException) {
            abort(404);
        } catch (TopicRatingUpstreamException $exception) {
            return $this->upstreamError($exception);
        }

        return response()->json([
            'topic_rating' => $result->toArray(),
        ]);
    }

    private function upstreamError(TopicRatingUpstreamException $exception): JsonResponse
    {
        return response()->json([
            'message' => $exception->getMessage(),
        ], $exception->httpStatus());
    }

    /**
     * validation 済み rating 値を整数として返す。
     *
     * @param array<string, mixed> $data
     */
    private function ratingValue(array $data): int
    {
        $rating = $data['rating'] ?? 0;

        return is_numeric($rating) ? (int) $rating : 0;
    }
}
