<?php

namespace App\Topics\Candidates;

use App\Models\CandidateTopic;
use App\Topics\Editorial\TopicEditorialAnalyzer;
use App\Topics\Screening\TopicScreeningEvaluator;
use App\Topics\Screening\TopicScreeningStatus;
use App\Topics\TopicBuilder;
use App\Topics\TopicDraft;
use App\Upstream\UpstreamArticleItem;
use App\Upstream\UpstreamArticleQuery;
use App\Upstream\UpstreamProvider;
use Carbon\CarbonImmutable;
use Throwable;

/**
 * Upstream article から CandidateTopic を作成・更新する。
 */
class CandidateTopicNominationService
{
    private UpstreamProvider $upstreamProvider;

    private TopicBuilder $topicBuilder;

    private TopicScreeningEvaluator $screeningEvaluator;

    private TopicEditorialAnalyzer $editorialAnalyzer;

    private StableJsonFingerprint $fingerprint;

    /**
     * Constructor.
     */
    public function __construct(
        UpstreamProvider $upstreamProvider,
        TopicBuilder $topicBuilder,
        TopicScreeningEvaluator $screeningEvaluator,
        TopicEditorialAnalyzer $editorialAnalyzer,
        StableJsonFingerprint $fingerprint,
    ) {
        $this->upstreamProvider = $upstreamProvider;
        $this->topicBuilder = $topicBuilder;
        $this->screeningEvaluator = $screeningEvaluator;
        $this->editorialAnalyzer = $editorialAnalyzer;
        $this->fingerprint = $fingerprint;
    }

    /**
     * CandidateTopic nomination を実行する。
     */
    public function nominate(CandidateTopicNominationInput $input): CandidateTopicNominationResult
    {
        $upstreamArticles = $this->upstreamProvider->fetch(new UpstreamArticleQuery(
            from: $input->from,
            to: $input->to,
            limit: $input->limit,
        ));

        $created = 0;
        $updated = 0;
        $unchanged = 0;
        $errors = [];

        foreach ($upstreamArticles as $upstreamArticle) {
            try {
                $topicDraft = $this->topicBuilder->build($upstreamArticle);
                $topicDraftJson = $topicDraft->toArray();
                $screening = $this->screeningEvaluator->evaluate($topicDraft);
                $screeningJson = $screening->toArray();
                $editorialJson = null;

                if ($screening->screeningStatus === TopicScreeningStatus::Passed) {
                    $editorial = $this->editorialAnalyzer->analyze($topicDraft);
                    $editorial->metadata = array_merge([
                        'topic_id' => $topicDraft->id,
                        'source_name' => $topicDraft->sourceName,
                        'url' => $topicDraft->url,
                        'discussion_url' => $topicDraft->discussionUrl,
                        'limitations' => $topicDraft->limitations,
                    ], $editorial->metadata);
                    $editorialJson = $editorial->toArray();
                }

                $candidateFingerprint = $this->candidateFingerprint($topicDraftJson, $screeningJson, $editorialJson);
                $existing = CandidateTopic::query()->where('topic_id', $topicDraft->id)->first();

                if ($existing instanceof CandidateTopic && ! $input->force && $existing->candidate_fingerprint === $candidateFingerprint) {
                    ++$unchanged;

                    continue;
                }

                $attributes = $this->attributes(
                    upstreamArticle: $upstreamArticle,
                    topicDraft: $topicDraft,
                    topicDraftJson: $topicDraftJson,
                    screeningJson: $screeningJson,
                    editorialJson: $editorialJson,
                    candidateFingerprint: $candidateFingerprint,
                    processedAt: $input->processedAt,
                );

                if ($existing instanceof CandidateTopic) {
                    $existing->update($attributes);
                    ++$updated;
                } else {
                    CandidateTopic::query()->create($attributes);
                    ++$created;
                }
            } catch (Throwable $exception) {
                $errors[] = [
                    'stage' => 'topic_nomination',
                    'topic_id' => 'upstream:' . $upstreamArticle->upstreamId,
                    'message' => $exception->getMessage(),
                ];
            }
        }

        return new CandidateTopicNominationResult(
            fetched: count($upstreamArticles),
            created: $created,
            updated: $updated,
            unchanged: $unchanged,
            errors: $errors,
        );
    }

    /**
     * @param array<string, mixed> $topicDraftJson
     * @param array<string, mixed> $screeningJson
     * @param array<string, mixed>|null $editorialJson
     */
    public function candidateFingerprint(array $topicDraftJson, array $screeningJson, ?array $editorialJson): string
    {
        return $this->fingerprint->hash([
            'topic_id' => $topicDraftJson['id'] ?? null,
            'source_refs' => $topicDraftJson['source_refs'] ?? null,
            'topic_draft_json' => $topicDraftJson,
            'screening_json' => $screeningJson,
            'editorial_json' => $editorialJson,
        ]);
    }

    /**
     * @param array<string, mixed> $topicDraftJson
     * @param array<string, mixed> $screeningJson
     * @param array<string, mixed>|null $editorialJson
     *
     * @return array<string, mixed>
     */
    private function attributes(
        UpstreamArticleItem $upstreamArticle,
        TopicDraft $topicDraft,
        array $topicDraftJson,
        array $screeningJson,
        ?array $editorialJson,
        string $candidateFingerprint,
        CarbonImmutable $processedAt,
    ): array {
        $sourceRefs = is_array($topicDraftJson['source_refs'] ?? null) ? $topicDraftJson['source_refs'] : [];

        return [
            'topic_id' => $topicDraft->id,
            'source_type' => $topicDraft->sourceType,
            'source_name' => $topicDraft->sourceName,
            'upstream_provider' => is_scalar($sourceRefs['provider'] ?? null) ? (string) $sourceRefs['provider'] : $upstreamArticle->providerName,
            'upstream_id' => is_scalar($sourceRefs['upstream_id'] ?? null) ? (string) $sourceRefs['upstream_id'] : (string) $upstreamArticle->upstreamId,
            'article_url' => $topicDraft->url,
            'article_published_at' => $topicDraft->publishedAt,
            'topic_draft_json' => $topicDraftJson,
            'screening_json' => $screeningJson,
            'editorial_json' => $editorialJson,
            'screening_status' => is_scalar($screeningJson['screening_status'] ?? null) ? (string) $screeningJson['screening_status'] : null,
            'screening_score' => is_numeric($screeningJson['screening_score'] ?? null) ? (int) $screeningJson['screening_score'] : null,
            'editorial_status' => is_scalar($editorialJson['status'] ?? null) ? (string) $editorialJson['status'] : null,
            'editorial_score' => is_numeric($editorialJson['editorial_score'] ?? null) ? (int) $editorialJson['editorial_score'] : null,
            'candidate_fingerprint' => $candidateFingerprint,
            'processed_at' => $processedAt,
            'metadata' => [
                'schema_version' => '1.0',
            ],
        ];
    }
}
