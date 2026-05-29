<?php

namespace App\Episodes;

use App\Models\CandidateTopic;
use App\Models\CharacterProfile;
use App\Models\Episode;
use App\Scenarios\ScenarioGenerationInput;
use App\Scenarios\ScenarioGenerator;
use App\Scenarios\ScenarioTopicSelection;
use App\Scenarios\ScenarioTopicSelector;
use App\Topics\Candidates\StableJsonFingerprint;
use App\Topics\Editorial\TopicEditorialEvaluation;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * 保存済み CandidateTopic から Scenario と Episode を作る。
 */
class CandidateEpisodeCompiler
{
    private ScenarioTopicSelector $topicSelector;

    private ScenarioGenerator $scenarioGenerator;

    private EpisodeRecorder $episodeRecorder;

    private StableJsonFingerprint $fingerprint;

    /**
     * Constructor.
     */
    public function __construct(
        ScenarioTopicSelector $topicSelector,
        ScenarioGenerator $scenarioGenerator,
        EpisodeRecorder $episodeRecorder,
        StableJsonFingerprint $fingerprint,
    ) {
        $this->topicSelector = $topicSelector;
        $this->scenarioGenerator = $scenarioGenerator;
        $this->episodeRecorder = $episodeRecorder;
        $this->fingerprint = $fingerprint;
    }

    /**
     * CandidateTopic から Scenario を生成する。
     */
    public function export(CharacterProfile $characterProfile, int $limit): CandidateEpisodeCompilationResult
    {
        $candidates = $this->candidateTopics($limit);
        $editorialEvaluations = $this->editorialEvaluations($candidates);
        $topicSelections = $this->topicSelector->select(
            $editorialEvaluations,
            $this->intConfig('radiopipe.scenario.max_topics', 5),
        );

        $scenarioResult = $this->scenarioGenerator->generate(new ScenarioGenerationInput(
            characterKey: $characterProfile->character_key,
            targetDurationSeconds: $this->intConfig('radiopipe.scenario.target_seconds', 900),
            title: null,
            language: 'ja',
            editorialEvaluations: $editorialEvaluations,
        ));

        $compileFingerprint = $this->compileFingerprint($candidates, $topicSelections, $characterProfile);

        return new CandidateEpisodeCompilationResult(
            scenarioResult: $scenarioResult,
            topicSelections: $topicSelections,
            candidateTopics: array_values($candidates->all()),
            pipelineItems: $this->pipelineItems($candidates, $topicSelections),
            compileFingerprint: $compileFingerprint,
            skipped: false,
            episode: null,
        );
    }

    /**
     * CandidateTopic input が変わっている場合だけ Episode を保存する。
     */
    public function compile(CharacterProfile $characterProfile, int $limit, CarbonImmutable $processedAt): CandidateEpisodeCompilationResult
    {
        $result = $this->export($characterProfile, $limit);
        $latest = $this->latestEpisode($characterProfile->character_key);
        $latestMetadata = $latest instanceof Episode ? $latest->getAttribute('metadata') : null;
        $latestMetadata = is_array($latestMetadata) ? $latestMetadata : [];

        if (($latestMetadata['compile_fingerprint'] ?? null) === $result->compileFingerprint) {
            return new CandidateEpisodeCompilationResult(
                scenarioResult: $result->scenarioResult,
                topicSelections: $result->topicSelections,
                candidateTopics: $result->candidateTopics,
                pipelineItems: $result->pipelineItems,
                compileFingerprint: $result->compileFingerprint,
                skipped: true,
                episode: $latest,
            );
        }

        $episode = $this->episodeRecorder->record(new EpisodeRecordInput(
            result: $result->scenarioResult,
            pipelineItems: $result->pipelineItems,
            characterProfile: $characterProfile,
            episodeKey: $this->episodeKey($processedAt, $characterProfile->character_key),
            date: $processedAt,
            publishedAt: $processedAt,
            processedAt: $processedAt,
            metadata: [
                'command' => 'radiopipe:episodes:compile',
                'compile_fingerprint' => $result->compileFingerprint,
                'candidate_topic_count' => count($result->candidateTopics),
                'generator' => $result->scenarioResult->metadata['generator'] ?? null,
            ],
        ));

        return new CandidateEpisodeCompilationResult(
            scenarioResult: $result->scenarioResult,
            topicSelections: $result->topicSelections,
            candidateTopics: $result->candidateTopics,
            pipelineItems: $result->pipelineItems,
            compileFingerprint: $result->compileFingerprint,
            skipped: false,
            episode: $episode,
        );
    }

    /**
     * @return Collection<int, CandidateTopic>
     */
    private function candidateTopics(int $limit): Collection
    {
        $query = CandidateTopic::query()
            ->where('editorial_status', 'pending');
        $query->getQuery()
            ->orderBy('editorial_score', 'desc')
            ->orderBy('processed_at', 'desc')
            ->limit(max(1, $limit));

        return $query->get();
    }

    /**
     * @param Collection<int, CandidateTopic> $candidates
     *
     * @return list<TopicEditorialEvaluation>
     */
    private function editorialEvaluations(Collection $candidates): array
    {
        $evaluations = [];

        foreach ($candidates as $candidate) {
            $evaluation = $candidate->editorialEvaluation();

            if ($evaluation instanceof TopicEditorialEvaluation) {
                $evaluations[] = $evaluation;
            }
        }

        return $evaluations;
    }

    /**
     * @param Collection<int, CandidateTopic> $candidates
     * @param list<ScenarioTopicSelection> $topicSelections
     *
     * @return list<array<string, mixed>>
     */
    private function pipelineItems(Collection $candidates, array $topicSelections): array
    {
        $selectionByTopicId = [];

        foreach ($topicSelections as $selection) {
            $selectionByTopicId[$selection->topicId] = $selection->toArray();
        }

        $items = [];

        foreach ($candidates as $candidate) {
            $items[] = [
                'upstream_article' => null,
                'topic_draft' => $candidate->topic_draft_json,
                'screening' => $candidate->screening_json,
                'editorial' => $candidate->editorial_json,
                'selection' => $selectionByTopicId[$candidate->topic_id] ?? null,
                'metadata' => [
                    'candidate_topic_id' => $candidate->id,
                    'candidate_fingerprint' => $candidate->candidate_fingerprint,
                ],
            ];
        }

        return $items;
    }

    /**
     * @param Collection<int, CandidateTopic> $candidates
     * @param list<ScenarioTopicSelection> $topicSelections
     */
    private function compileFingerprint(Collection $candidates, array $topicSelections, CharacterProfile $characterProfile): string
    {
        return $this->fingerprint->hash([
            'candidate_topics' => $candidates
                ->map(static fn (CandidateTopic $candidate): array => [
                    'id' => $candidate->id,
                    'topic_id' => $candidate->topic_id,
                    'candidate_fingerprint' => $candidate->candidate_fingerprint,
                ])
                ->values()
                ->all(),
            'topic_selections' => array_map(
                static fn (ScenarioTopicSelection $selection): array => $selection->toArray(),
                $topicSelections,
            ),
            'character' => [
                'id' => $characterProfile->id,
                'character_key' => $characterProfile->character_key,
                'updated_at' => $characterProfile->updated_at?->toJSON(),
            ],
            'scenario' => [
                'generator' => config('radiopipe.scenario.generator', 'fake'),
                'model' => config('radiopipe.scenario.model'),
                'max_topics' => config('radiopipe.scenario.max_topics'),
                'target_seconds' => config('radiopipe.scenario.target_seconds'),
            ],
        ]);
    }

    private function latestEpisode(?string $characterKey): ?Episode
    {
        $query = Episode::query()->where('character_key', $characterKey);
        $query->getQuery()->orderBy('created_at', 'desc');

        return $query->first();
    }

    private function episodeKey(CarbonImmutable $processedAt, ?string $characterKey): string
    {
        $safeCharacterKey = preg_replace('/[^A-Za-z0-9_-]+/', '-', $characterKey ?? 'anonymous');
        $safeCharacterKey = trim((string) $safeCharacterKey, '-_');

        if ($safeCharacterKey === '') {
            $safeCharacterKey = 'anonymous';
        }

        return sprintf('episode_%s_%s', $processedAt->format('Y-m-d_His'), $safeCharacterKey);
    }

    private function intConfig(string $key, int $default): int
    {
        $value = config($key, $default);

        return is_numeric($value) ? (int) $value : $default;
    }
}
