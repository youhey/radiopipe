<?php

namespace App\Episodes;

use App\Models\Episode;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * 生成済み Scenario と topic snapshot を Episode として永続化する。
 */
class EpisodeRecorder
{
    /**
     * 生成済み scenario result と中間 snapshot を保存する。
     */
    public function record(EpisodeRecordInput $input): Episode
    {
        return DB::transaction(function () use ($input): Episode {
            $scenario = $input->result->scenario;
            $characterKey = $input->characterProfile === null
                ? $scenario->characterKey
                : $input->characterProfile->character_key;
            $episodeKey = $input->episodeKey ?? $this->episodeKey($input->processedAt, $characterKey);
            $errors = $this->safeList($input->errors);

            $episode = Episode::query()->create([
                'episode_key' => $episodeKey,
                'date' => $input->date,
                'published_at' => $input->publishedAt,
                'processed_at' => $input->processedAt,
                'character_profile_id' => $input->characterProfile?->id,
                'character_key' => $characterKey,
                'status' => $errors === [] ? Episode::STATUS_COMPLETED : Episode::STATUS_COMPLETED_WITH_ERRORS,
                'title' => $scenario->title,
                'language' => $scenario->language,
                'target_duration_seconds' => $scenario->targetDurationSeconds,
                'estimated_duration_seconds' => $scenario->estimatedDurationSeconds,
                'scenario_json' => $this->safeArray($scenario->toArray()),
                'metadata' => $this->safeArray($input->metadata),
                'errors' => $errors === [] ? null : $errors,
            ]);

            foreach ($input->pipelineItems as $index => $item) {
                $episode->topics()->create($this->topicAttributes($item, $index));
            }

            return $episode->load('topics');
        });
    }

    /**
     * processed_at と character key から deterministic な episode key を作る。
     */
    public function episodeKey(CarbonImmutable $processedAt, ?string $characterKey): string
    {
        $safeCharacterKey = preg_replace('/[^A-Za-z0-9_-]+/', '-', $characterKey ?? 'anonymous');
        $safeCharacterKey = trim((string) $safeCharacterKey, '-_');

        if ($safeCharacterKey === '') {
            $safeCharacterKey = 'anonymous';
        }

        return sprintf('episode_%s_%s', $processedAt->format('Y-m-d_Hi'), $safeCharacterKey);
    }

    /**
     * @param array<string, mixed> $item
     *
     * @return array<string, mixed>
     */
    private function topicAttributes(array $item, int $index): array
    {
        $topicDraft = $this->nullableArray($item['topic_draft'] ?? null);
        $upstreamArticle = $this->nullableArray($item['upstream_article'] ?? null);
        $screening = $this->nullableArray($item['screening'] ?? null);
        $editorial = $this->nullableArray($item['editorial'] ?? null);
        $selection = $this->nullableArray($item['selection'] ?? null);
        $sourceRefs = $this->nullableArray($topicDraft['source_refs'] ?? null);
        $source = $this->nullableArray($upstreamArticle['source'] ?? null);

        return [
            'topic_id' => $this->stringValue($topicDraft['id'] ?? ($selection['topic_id'] ?? 'topic:' . ($index + 1))),
            'upstream_provider' => $this->nullableString($sourceRefs['provider'] ?? ($upstreamArticle['provider_name'] ?? null)),
            'upstream_id' => $this->nullableScalarString($sourceRefs['upstream_id'] ?? ($upstreamArticle['upstream_id'] ?? null)),
            'source_name' => $this->nullableString($topicDraft['source_name'] ?? ($source['name'] ?? null)),
            'source_type' => $this->nullableString($topicDraft['source_type'] ?? null),
            'title' => $this->nullableString($topicDraft['title'] ?? null),
            'url' => $this->nullableString($topicDraft['url'] ?? null),
            'screening_status' => $this->nullableString($screening['screening_status'] ?? null),
            'editorial_status' => $this->nullableString($editorial['status'] ?? null),
            'scenario_selection_status' => $this->nullableString($selection['status'] ?? null),
            'sort_order' => $index,
            'topic_draft_json' => $this->safeNullableArray($topicDraft),
            'screening_json' => $this->safeNullableArray($screening),
            'editorial_json' => $this->safeNullableArray($editorial),
            'scenario_selection_json' => $this->safeNullableArray($selection),
            'metadata' => $this->safeNullableArray($this->nullableArray($item['metadata'] ?? null)),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function nullableArray(mixed $value): ?array
    {
        if (! is_array($value)) {
            return null;
        }

        /** @var array<string, mixed> $value */
        return $value;
    }

    /**
     * @param array<string, mixed>|null $value
     *
     * @return array<string, mixed>|null
     */
    private function safeNullableArray(?array $value): ?array
    {
        return $value === null ? null : $this->safeArray($value);
    }

    /**
     * @param array<string, mixed> $value
     *
     * @return array<string, mixed>
     */
    private function safeArray(array $value): array
    {
        $safe = $this->safeValue($value);

        if (! is_array($safe)) {
            return [];
        }

        /** @var array<string, mixed> $safe */
        return $safe;
    }

    /**
     * @param list<array<string, mixed>> $values
     *
     * @return list<array<string, mixed>>
     */
    private function safeList(array $values): array
    {
        return array_map(
            fn (array $value): array => $this->safeArray($value),
            $values,
        );
    }

    private function safeValue(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        $safe = [];

        foreach ($value as $key => $item) {
            if (is_string($key) && $this->isSensitiveKey($key)) {
                continue;
            }

            $safe[$key] = $this->safeValue($item);
        }

        return $safe;
    }

    private function isSensitiveKey(string $key): bool
    {
        return in_array(strtolower($key), [
            'api_key',
            'authorization',
            'access_token',
            'refresh_token',
            'token',
            'client_secret',
            'secret',
            'prompt',
            'prompts',
            'raw_prompt',
            'raw_model_response',
            'raw_response',
            'raw_article_body',
            'article_body',
            'body',
        ], true);
    }

    private function stringValue(mixed $value): string
    {
        if (is_scalar($value)) {
            return (string) $value;
        }

        return 'unknown';
    }

    private function nullableString(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    private function nullableScalarString(mixed $value): ?string
    {
        return is_scalar($value) && (string) $value !== '' ? (string) $value : null;
    }
}
