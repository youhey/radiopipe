<?php

namespace App\Console\Commands;

use App\Models\CharacterProfile;
use App\Scenarios\ScenarioGenerationInput;
use App\Scenarios\ScenarioGenerator;
use App\Scenarios\ScenarioTopicSelection;
use App\Scenarios\ScenarioTopicSelectionStatus;
use App\Scenarios\ScenarioTopicSelector;
use App\Topics\Editorial\TopicEditorialAnalyzer;
use App\Topics\Editorial\TopicEditorialEvaluation;
use App\Topics\Editorial\TopicEditorialStatus;
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
 * Scenario generation pipeline の中間データを JSON で出力するデバッグコマンド。
 */
class ScenarioDebugCommand extends Command
{
    protected $signature = 'radiopipe:scenario:debug
        {--from= : Start datetime for upstream article fetch}
        {--to= : End datetime for upstream article fetch}
        {--limit= : Maximum number of upstream articles to fetch}
        {--character= : Character profile key to use}';

    protected $description = 'Inspect the configured scenario generation pipeline as JSON.';

    private UpstreamProvider $upstreamProvider;

    private TopicBuilder $topicBuilder;

    private TopicScreeningEvaluator $screeningEvaluator;

    private TopicEditorialAnalyzer $editorialAnalyzer;

    private ScenarioTopicSelector $topicSelector;

    private ScenarioGenerator $scenarioGenerator;

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
    ) {
        parent::__construct();

        $this->upstreamProvider = $upstreamProvider;
        $this->topicBuilder = $topicBuilder;
        $this->screeningEvaluator = $screeningEvaluator;
        $this->editorialAnalyzer = $editorialAnalyzer;
        $this->topicSelector = $topicSelector;
        $this->scenarioGenerator = $scenarioGenerator;
    }

    /**
     * Configured scenario pipeline を実行して JSON を標準出力へ出力する。
     */
    public function handle(): int
    {
        $timezone = $this->timezone();
        $now = CarbonImmutable::now($timezone);
        $to = $this->dateOption('to') ?? $now;
        $from = $this->dateOption('from') ?? $to->subDay();
        $limit = $this->limitOption();
        $character = $this->characterProfile();

        if (! $character instanceof CharacterProfile) {
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

        $scenario = null;

        try {
            $result = $this->scenarioGenerator->generate(new ScenarioGenerationInput(
                characterKey: $character->character_key,
                targetDurationSeconds: $this->intConfig('radiopipe.scenario.target_seconds', 900),
                title: null,
                language: 'ja',
                editorialEvaluations: $editorialEvaluations,
            ));
            $scenario = $result->scenario->toArray();
        } catch (Throwable $exception) {
            $errors[] = [
                'stage' => 'scenario',
                'message' => $exception->getMessage(),
            ];
        }

        $output = [
            'schema_version' => '1.0',
            'generated_at' => $now->toAtomString(),
            'input' => [
                'from' => $from->toAtomString(),
                'to' => $to->toAtomString(),
                'limit' => $limit,
                'character_key' => $character->character_key,
            ],
            'character' => [
                'character_key' => $character->character_key,
                'name' => $character->name,
            ],
            'counts' => [
                'upstream_articles' => count($upstreamArticles),
                'topic_drafts' => $this->countPresent($items, 'topic_draft'),
                'screening_passed' => $this->countScreeningPassed($items),
                'editorial_pending' => $this->countEditorialPending($editorialEvaluations),
                'selected_topics' => $this->countUsedSelections($topicSelections),
            ],
            'items' => $items,
            'scenario' => $scenario,
            'errors' => $errors,
        ];

        try {
            $this->line(json_encode($output, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        } catch (JsonException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        return $errors === [] ? self::SUCCESS : self::FAILURE;
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
     * @param list<array<string, mixed>> $items
     */
    private function countPresent(array $items, string $key): int
    {
        return count(array_filter(
            $items,
            static fn (array $item): bool => ($item[$key] ?? null) !== null,
        ));
    }

    /**
     * @param list<array<string, mixed>> $items
     */
    private function countScreeningPassed(array $items): int
    {
        return count(array_filter(
            $items,
            static function (array $item): bool {
                $screening = $item['screening'] ?? null;

                return is_array($screening) && (($screening['screening_status'] ?? null) === TopicScreeningStatus::Passed->value);
            },
        ));
    }

    /**
     * @param list<TopicEditorialEvaluation> $editorialEvaluations
     */
    private function countEditorialPending(array $editorialEvaluations): int
    {
        return count(array_filter(
            $editorialEvaluations,
            static fn (TopicEditorialEvaluation $editorial): bool => $editorial->status === TopicEditorialStatus::Pending,
        ));
    }

    /**
     * @param list<ScenarioTopicSelection> $topicSelections
     */
    private function countUsedSelections(array $topicSelections): int
    {
        return count(array_filter(
            $topicSelections,
            static fn (ScenarioTopicSelection $selection): bool => $selection->status === ScenarioTopicSelectionStatus::UsedInScenario,
        ));
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
