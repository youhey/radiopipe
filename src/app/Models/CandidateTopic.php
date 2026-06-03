<?php

namespace App\Models;

use App\Topics\Editorial\TopicDuplicateAssessment;
use App\Topics\Editorial\TopicEditorialEvaluation;
use App\Topics\Editorial\TopicEditorialFlags;
use App\Topics\Editorial\TopicEditorialScores;
use App\Topics\Editorial\TopicEditorialStatus;
use App\Topics\Editorial\TopicLocalizedText;
use App\Topics\Editorial\TopicScenarioNotes;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * Episode 生成前に再利用する topic candidate の永続化モデル。
 */
#[Fillable([
    'topic_id',
    'source_type',
    'source_name',
    'upstream_provider',
    'upstream_id',
    'article_url',
    'article_published_at',
    'topic_draft_json',
    'screening_json',
    'editorial_json',
    'screening_status',
    'screening_score',
    'editorial_status',
    'editorial_score',
    'candidate_fingerprint',
    'processed_at',
    'metadata',
])]
class CandidateTopic extends Model
{
    /**
     * 保存済み editorial snapshot を ScenarioTopicSelector 用の value object に戻す。
     */
    public function editorialEvaluation(): ?TopicEditorialEvaluation
    {
        $editorial = $this->arrayAttribute('editorial_json');

        if ($editorial === null) {
            return null;
        }

        $status = TopicEditorialStatus::tryFrom($this->stringValue($editorial['status'] ?? null));

        if (! $status instanceof TopicEditorialStatus) {
            return null;
        }

        $localized = $this->arrayValue($editorial['localized'] ?? null);
        $scores = $this->arrayValue($editorial['scores'] ?? null);
        $flags = $this->arrayValue($editorial['flags'] ?? null);
        $duplicate = $this->arrayValue($editorial['duplicate'] ?? null);
        $scenarioNotes = $this->arrayValue($editorial['scenario_notes'] ?? null);

        return new TopicEditorialEvaluation(
            status: $status,
            editorialScore: $this->intValue($editorial['editorial_score'] ?? null),
            localized: new TopicLocalizedText(
                title: $this->stringValue($localized['title'] ?? null),
                summary: $this->stringValue($localized['summary'] ?? null),
                whyItMatters: $this->stringValue($localized['why_it_matters'] ?? null),
            ),
            scores: new TopicEditorialScores(
                preference: $this->intValue($scores['preference'] ?? null),
                generalImportance: $this->intValue($scores['general_importance'] ?? null),
                freshness: $this->intValue($scores['freshness'] ?? null),
                certainty: $this->intValue($scores['certainty'] ?? null),
                scenarioFitness: $this->intValue($scores['scenario_fitness'] ?? null),
                flowFlexibility: $this->intValue($scores['flow_flexibility'] ?? null),
            ),
            flags: new TopicEditorialFlags(
                isDuplicateCandidate: $this->boolValue($flags['is_duplicate_candidate'] ?? null),
                isUncertain: $this->boolValue($flags['is_uncertain'] ?? null),
                isSensitive: $this->boolValue($flags['is_sensitive'] ?? null),
            ),
            duplicate: new TopicDuplicateAssessment(
                canonicalKey: $this->nullableString($duplicate['canonical_key'] ?? null),
                similarTopicIds: $this->stringList($duplicate['similar_topic_ids'] ?? null),
                duplicateOf: $this->nullableString($duplicate['duplicate_of'] ?? null),
                confidence: $this->nullableInt($duplicate['confidence'] ?? null),
                reason: $this->nullableString($duplicate['reason'] ?? null),
            ),
            scenarioNotes: new TopicScenarioNotes(
                suggestedRole: $this->nullableString($scenarioNotes['suggested_role'] ?? null),
                tone: $this->nullableString($scenarioNotes['tone'] ?? null),
                transitionHint: $this->nullableString($scenarioNotes['transition_hint'] ?? null),
                avoid: $this->stringList($scenarioNotes['avoid'] ?? null),
            ),
            reasons: $this->stringList($editorial['reasons'] ?? null),
            metadata: array_merge(
                ['topic_id' => $this->topic_id],
                $this->arrayValue($editorial['metadata'] ?? null),
            ),
        );
    }

    /**
     * 過去の完了 Episode で実際に使われた CandidateTopic を除外する。
     *
     * @param Builder<CandidateTopic> $query
     */
    public static function excludeUsedInCompletedEpisodes(Builder $query): void
    {
        $query->getQuery()->whereNotExists(static function (QueryBuilder $query): void {
            $query
                ->selectRaw('1')
                ->from('episode_topics')
                ->join('episodes', 'episodes.id', '=', 'episode_topics.episode_id')
                ->where('episode_topics.scenario_selection_status', 'used_in_scenario')
                ->whereIn('episodes.status', [
                    Episode::STATUS_COMPLETED,
                    Episode::STATUS_COMPLETED_WITH_ERRORS,
                ])
                ->where(static function (QueryBuilder $query): void {
                    $query
                        ->whereColumn('episode_topics.topic_id', 'candidate_topics.topic_id')
                        ->orWhereColumn('episode_topics.topic_id', 'candidate_topics.id')
                        ->orWhere(static function (QueryBuilder $query): void {
                            $query
                                ->whereNotNull('candidate_topics.upstream_provider')
                                ->whereNotNull('candidate_topics.upstream_id')
                                ->whereColumn('episode_topics.upstream_provider', 'candidate_topics.upstream_provider')
                                ->whereColumn('episode_topics.upstream_id', 'candidate_topics.upstream_id');
                        });
                });
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'article_published_at' => 'datetime',
            'topic_draft_json' => 'array',
            'screening_json' => 'array',
            'editorial_json' => 'array',
            'screening_score' => 'integer',
            'editorial_score' => 'integer',
            'processed_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function arrayAttribute(string $key): ?array
    {
        $value = $this->getAttribute($key);

        if (! is_array($value)) {
            return null;
        }

        /** @var array<string, mixed> $value */
        return $value;
    }

    /**
     * @return array<string, mixed>
     */
    private function arrayValue(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        /** @var array<string, mixed> $value */
        return $value;
    }

    private function stringValue(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }

    private function nullableString(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    private function intValue(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    private function nullableInt(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }

    private function boolValue(mixed $value): bool
    {
        return is_bool($value) ? $value : false;
    }

    /**
     * @return list<string>
     */
    private function stringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $strings = [];

        foreach ($value as $item) {
            if (is_string($item)) {
                $strings[] = $item;
            }
        }

        return $strings;
    }
}
