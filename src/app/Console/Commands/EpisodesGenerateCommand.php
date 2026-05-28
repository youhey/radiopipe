<?php

namespace App\Console\Commands;

use App\Episodes\EpisodeRecorder;
use App\Episodes\EpisodeRecordInput;
use App\Models\CharacterProfile;
use App\Models\Episode;
use App\Scenarios\ScenarioGenerationInput;
use App\Scenarios\ScenarioGenerationResult;
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
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use JsonException;
use Throwable;

/**
 * Configured pipeline を実行して Episode を生成・永続化するコマンド。
 */
class EpisodesGenerateCommand extends Command
{
    protected $signature = 'radiopipe:episodes:generate
        {--from= : Start datetime for upstream article fetch}
        {--to= : End datetime for upstream article fetch}
        {--limit= : Maximum number of upstream articles to fetch}
        {--character= : Character profile key to use}
        {--published-at= : Published datetime for the episode}
        {--dry-run : Run the pipeline and print JSON without saving}';

    protected $description = 'Generate and persist an episode using the configured radiopipe pipeline.';

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
        parent::__construct();

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
    public function handle(): int
    {
        $timezone = $this->timezone();
        $now = CarbonImmutable::now($timezone);
        $to = $this->dateOption('to') ?? $now;
        $from = $this->dateOption('from') ?? $to->subDay();
        $limit = $this->limitOption();
        $publishedAt = $this->dateOption('published-at') ?? $now;
        $character = $this->characterProfile();

        if (! $character instanceof CharacterProfile) {
            return self::FAILURE;
        }

        $episodeKey = $this->episodeRecorder->episodeKey($publishedAt, $character->character_key);

        $existingEpisodeQuery = Episode::query()->where('episode_key', $episodeKey);

        if (! $this->option('dry-run') && $existingEpisodeQuery->getQuery()->exists()) {
            $this->error("Episode [{$episodeKey}] already exists.");

            return self::FAILURE;
        }

        try {
            $upstreamArticles = $this->upstreamProvider->fetch(new UpstreamArticleQuery(
                from: $from,
                to: $to,
                limit: $limit,
            ));
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

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

        try {
            $result = $this->scenarioGenerator->generate(new ScenarioGenerationInput(
                characterKey: $character->character_key,
                targetDurationSeconds: $this->intConfig('radiopipe.scenario.target_seconds', 900),
                title: null,
                language: 'ja',
                editorialEvaluations: $editorialEvaluations,
            ));
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        if ($this->option('dry-run')) {
            return $this->printDryRun($now, $from, $to, $limit, $publishedAt, $character, $result, $topicSelections, $errors);
        }

        try {
            $episode = $this->episodeRecorder->record(new EpisodeRecordInput(
                result: $result,
                pipelineItems: $items,
                characterProfile: $character,
                episodeKey: $episodeKey,
                date: $publishedAt,
                publishedAt: $publishedAt,
                processedAt: $now,
                metadata: [
                    'command' => 'radiopipe:episodes:generate',
                    'generator' => $result->metadata['generator'] ?? null,
                ],
                errors: $errors,
            ));
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->line("Episode generated: id={$episode->id} key={$episode->episode_key} status={$episode->status}");

        return self::SUCCESS;
    }

    /**
     * @param list<ScenarioTopicSelection> $topicSelections
     * @param list<array<string, mixed>> $errors
     */
    private function printDryRun(
        CarbonImmutable $now,
        CarbonImmutable $from,
        CarbonImmutable $to,
        int $limit,
        CarbonImmutable $publishedAt,
        CharacterProfile $character,
        ScenarioGenerationResult $result,
        array $topicSelections,
        array $errors,
    ): int {
        $output = [
            'schema_version' => '1.0',
            'dry_run' => true,
            'generated_at' => $now->toAtomString(),
            'input' => [
                'from' => $from->toAtomString(),
                'to' => $to->toAtomString(),
                'limit' => $limit,
                'character_key' => $character->character_key,
                'published_at' => $publishedAt->toAtomString(),
            ],
            'scenario' => $result->scenario->toArray(),
            'topic_selections' => array_map(
                static fn (ScenarioTopicSelection $selection): array => $selection->toArray(),
                $topicSelections,
            ),
            'errors' => $errors,
        ];

        try {
            $this->line(json_encode($output, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        } catch (JsonException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function characterProfile(): ?CharacterProfile
    {
        $characterKey = $this->option('character');
        $query = CharacterProfile::query()->where('is_active', true);

        if (is_string($characterKey) && trim($characterKey) !== '') {
            $profile = $query->where('character_key', trim($characterKey))->first();

            if (! $profile instanceof CharacterProfile) {
                $this->error("Active character profile [{$characterKey}] was not found.");

                return null;
            }

            return $profile;
        }

        $query->getQuery()
            ->orderBy('sort_order')
            ->orderBy('name');

        $profile = $query->first();

        if (! $profile instanceof CharacterProfile) {
            $this->error('No active character profile was found.');

            return null;
        }

        return $profile;
    }

    private function dateOption(string $name): ?CarbonImmutable
    {
        $value = $this->option($name);

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return CarbonImmutable::parse($value, $this->timezone());
    }

    private function timezone(): string
    {
        $timezone = config('app.timezone', 'UTC');

        return is_string($timezone) && $timezone !== '' ? $timezone : 'UTC';
    }

    private function limitOption(): int
    {
        $value = $this->option('limit');

        if (! is_string($value) || trim($value) === '') {
            return 20;
        }

        return max(1, (int) $value);
    }

    private function intConfig(string $key, int $default): int
    {
        $value = config($key, $default);

        return is_int($value) ? $value : $default;
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
