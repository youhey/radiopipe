<?php

namespace App\Episodes;

use App\Models\Episode;
use App\Scenarios\ScenarioGenerationInput as ScenarioInput;
use App\Scenarios\ScenarioGenerator;
use App\Scenarios\ScenarioTopicSelection;
use App\Scenarios\ScenarioTopicSelector;
use App\Topics\Editorial\TopicEditorialAnalyzer;
use App\Topics\Editorial\TopicEditorialEvaluation;
use App\Topics\Screening\TopicScreeningEvaluator;
use App\Topics\Screening\TopicScreeningStatus;
use App\Topics\TopicBuilder;
use App\Topics\TopicDraft;
use App\Upstream\UpstreamArticleItem;
use App\Upstream\UpstreamArticleQuery;
use App\Upstream\UpstreamProvider;
use RuntimeException;
use Throwable;

/**
 * Configured pipeline を実行し、必要に応じて Episode として永続化する。
 */
class EpisodeGenerationService
{
    private UpstreamProvider $upstreamProvider;

    private TopicBuilder $topicBuilder;

    private TopicScreeningEvaluator $screeningEvaluator;

    private TopicEditorialAnalyzer $editorialAnalyzer;

    private ScenarioTopicSelector $topicSelector;

    private ScenarioGenerator $scenarioGenerator;

    private EpisodeRecorder $episodeRecorder;

    /**
     * Constructor.
     */
    public function __construct(
        UpstreamProvider $upstreamProvider,
        TopicBuilder $topicBuilder,
        TopicScreeningEvaluator $screeningEvaluator,
        TopicEditorialAnalyzer $editorialAnalyzer,
        ScenarioTopicSelector $topicSelector,
        ScenarioGenerator $scenarioGenerator,
        EpisodeRecorder $episodeRecorder,
    ) {
        $this->upstreamProvider = $upstreamProvider;
        $this->topicBuilder = $topicBuilder;
        $this->screeningEvaluator = $screeningEvaluator;
        $this->editorialAnalyzer = $editorialAnalyzer;
        $this->topicSelector = $topicSelector;
        $this->scenarioGenerator = $scenarioGenerator;
        $this->episodeRecorder = $episodeRecorder;
    }

    /**
     * Episode generation pipeline を実行する。
     */
    public function generate(EpisodeGenerationInput $input): EpisodeGenerationRunResult
    {
        $episodeKey = $this->episodeRecorder->episodeKey($input->publishedAt, $input->characterProfile->character_key);

        if ($input->persist && Episode::query()->where('episode_key', $episodeKey)->getQuery()->exists()) {
            throw new RuntimeException("Episode [{$episodeKey}] already exists.");
        }

        $upstreamArticles = $this->upstreamProvider->fetch(new UpstreamArticleQuery(
            from: $input->from,
            to: $input->to,
            limit: $input->limit,
        ));

        $items = [];
        $errors = [];
        $editorialEvaluations = [];

        foreach ($upstreamArticles as $upstreamArticle) {
            $item = [
                'upstream_article' => $upstreamArticle->toArray(),
                'topic_draft' => null,
                'screening' => null,
                'editorial' => null,
                'selection' => null,
            ];

            try {
                $topicDraft = $this->topicBuilder->build($upstreamArticle);
                $item['topic_draft'] = $topicDraft->toArray();

                $screening = $this->screeningEvaluator->evaluate($topicDraft);
                $item['screening'] = $screening->toArray();

                if ($screening->screeningStatus === TopicScreeningStatus::Passed) {
                    $editorial = $this->withDebugMetadata($this->editorialAnalyzer->analyze($topicDraft), $topicDraft);
                    $editorialEvaluations[] = $editorial;
                    $item['editorial'] = $editorial->toArray();
                }
            } catch (Throwable $exception) {
                $errors[] = $this->errorItem($exception, $item, $upstreamArticle);
            }

            $items[] = $item;
        }

        $topicSelections = $this->topicSelector->select(
            $editorialEvaluations,
            $this->intConfig('radiopipe.scenario.max_topics', 5),
        );
        $this->applySelections($items, $topicSelections);

        $scenarioResult = $this->scenarioGenerator->generate(new ScenarioInput(
            characterKey: $input->characterProfile->character_key,
            targetDurationSeconds: $this->intConfig('radiopipe.scenario.target_seconds', 900),
            title: null,
            language: 'ja',
            editorialEvaluations: $editorialEvaluations,
        ));

        $episode = null;

        if ($input->persist) {
            $episode = $this->episodeRecorder->record(new EpisodeRecordInput(
                result: $scenarioResult,
                pipelineItems: $items,
                characterProfile: $input->characterProfile,
                episodeKey: $episodeKey,
                date: $input->publishedAt,
                publishedAt: $input->publishedAt,
                processedAt: $input->processedAt,
                metadata: [
                    'command' => $input->commandName,
                    'generator' => $scenarioResult->metadata['generator'] ?? null,
                ],
                errors: $errors,
            ));
        }

        return new EpisodeGenerationRunResult(
            scenarioResult: $scenarioResult,
            topicSelections: $topicSelections,
            pipelineItems: $items,
            errors: $errors,
            episodeKey: $episodeKey,
            episode: $episode,
        );
    }

    private function withDebugMetadata(TopicEditorialEvaluation $editorial, TopicDraft $topicDraft): TopicEditorialEvaluation
    {
        $editorial->metadata = array_merge([
            'topic_id' => $topicDraft->id,
            'source_name' => $topicDraft->sourceName,
            'url' => $topicDraft->url,
            'discussion_url' => $topicDraft->discussionUrl,
            'limitations' => $topicDraft->limitations,
        ], $editorial->metadata);

        return $editorial;
    }

    /**
     * @param list<array<string, mixed>> $items
     * @param list<ScenarioTopicSelection> $topicSelections
     */
    private function applySelections(array &$items, array $topicSelections): void
    {
        $selectionByTopicId = [];

        foreach ($topicSelections as $selection) {
            $selectionByTopicId[$selection->topicId] = $selection->toArray();
        }

        foreach ($items as &$item) {
            $topicDraft = $item['topic_draft'] ?? null;

            if (! is_array($topicDraft)) {
                continue;
            }

            $topicId = $topicDraft['id'] ?? null;

            if (is_string($topicId) && isset($selectionByTopicId[$topicId])) {
                $item['selection'] = $selectionByTopicId[$topicId];
            }
        }
    }

    private function intConfig(string $key, int $default): int
    {
        $value = config($key, $default);

        return is_int($value) ? $value : $default;
    }

    /**
     * @param array<string, mixed> $item
     *
     * @return array{stage: string, topic_id: string|null, message: string}
     */
    private function errorItem(Throwable $exception, array $item, UpstreamArticleItem $upstreamArticle): array
    {
        $topicDraft = $item['topic_draft'] ?? null;
        $screening = $item['screening'] ?? null;
        $stage = 'topic_building';
        $topicId = null;

        if (is_array($topicDraft)) {
            $stage = is_array($screening) ? 'editorial' : 'screening';
            $topicId = is_string($topicDraft['id'] ?? null) ? $topicDraft['id'] : null;
        }

        return [
            'stage' => $stage,
            'topic_id' => $topicId ?? 'upstream:' . $upstreamArticle->upstreamId,
            'message' => $exception->getMessage(),
        ];
    }
}
