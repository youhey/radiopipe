<?php

namespace Tests\Feature\Console;

use App\Models\CharacterProfile;
use App\Models\Episode;
use App\Models\EpisodeTopic;
use App\Scenarios\Scenario;
use App\Scenarios\ScenarioGenerationInput;
use App\Scenarios\ScenarioGenerationResult;
use App\Scenarios\ScenarioGenerator;
use App\Scenarios\ScenarioSection;
use App\Scenarios\ScenarioTopicSelector;
use App\Topics\Editorial\TopicDuplicateAssessment;
use App\Topics\Editorial\TopicEditorialAnalyzer;
use App\Topics\Editorial\TopicEditorialEvaluation;
use App\Topics\Editorial\TopicEditorialFlags;
use App\Topics\Editorial\TopicEditorialScores;
use App\Topics\Editorial\TopicEditorialStatus;
use App\Topics\Editorial\TopicLocalizedText;
use App\Topics\Editorial\TopicScenarioNotes;
use App\Topics\Screening\TopicScreeningEvaluation;
use App\Topics\Screening\TopicScreeningEvaluator;
use App\Topics\Screening\TopicScreeningStatus;
use App\Topics\TopicBuilder;
use App\Topics\TopicDraft;
use App\Upstream\UpstreamArticleItem;
use App\Upstream\UpstreamArticleQuery;
use App\Upstream\UpstreamProvider;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use RuntimeException;
use Tests\TestCase;

/**
 * @internal
 */
class EpisodesGenerateCommandTest extends TestCase
{
    use RefreshDatabase;

    public function testCommandExists(): void
    {
        $this->profile();
        $this->bindPipeline();

        self::assertSame(0, Artisan::call('radiopipe:episodes:generate', [
            '--help' => true,
        ]));
    }

    public function testItFailsClearlyWhenNoActiveCharacterProfileExists(): void
    {
        $this->bindPipeline();

        self::assertSame(1, Artisan::call('radiopipe:episodes:generate'));
        self::assertStringContainsString('No active character profile was found.', Artisan::output());
    }

    public function testItFailsClearlyWhenSpecifiedCharacterIsMissingOrInactive(): void
    {
        $this->profile('inactive_character', '非活性', active: false);
        $this->bindPipeline();

        self::assertSame(1, Artisan::call('radiopipe:episodes:generate', [
            '--character' => 'inactive_character',
        ]));
        self::assertStringContainsString('Active character profile [inactive_character] was not found.', Artisan::output());
    }

    public function testItSupportsExplicitOptions(): void
    {
        config(['app.timezone' => 'Asia/Tokyo']);
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-05-28T12:00:00+09:00'));
        $this->profile('explicit_character', '明示');
        $upstream = new EpisodeGenerateRecordingUpstreamProvider([$this->upstreamItem(1)]);
        $scenarioGenerator = new EpisodeGenerateRecordingScenarioGenerator();
        $this->bindPipeline(upstreamProvider: $upstream, scenarioGenerator: $scenarioGenerator);

        self::assertSame(0, Artisan::call('radiopipe:episodes:generate', [
            '--from' => '2026-05-26T07:00:00+09:00',
            '--to' => '2026-05-27T07:00:00+09:00',
            '--limit' => '3',
            '--character' => 'explicit_character',
            '--published-at' => '2026-05-28T07:00:00+09:00',
        ]));

        $episode = Episode::query()->firstOrFail();
        self::assertSame('episode_2026-05-28_0700_explicit_character', $episode->episode_key);
        self::assertSame('explicit_character', $episode->character_key);
        self::assertNotNull($upstream->lastQuery);
        self::assertSame(3, $upstream->lastQuery->limit);
        self::assertSame('2026-05-25T22:00:00.000000Z', $upstream->lastQuery->from?->toJSON());
        self::assertSame('explicit_character', $scenarioGenerator->lastInput?->characterKey);

        CarbonImmutable::setTestNow();
    }

    public function testDryRunDoesNotPersistEpisodeAndOutputsValidJson(): void
    {
        $this->profile();
        $this->bindPipeline();

        self::assertSame(0, Artisan::call('radiopipe:episodes:generate', [
            '--dry-run' => true,
        ]));

        $this->assertDatabaseCount('episodes', 0);
        $output = $this->jsonOutput();

        self::assertSame('1.0', $output['schema_version']);
        self::assertTrue($output['dry_run']);
        self::assertIsArray($output['scenario']);
        self::assertIsArray($output['topic_selections']);
        self::assertSame([], $output['errors']);
    }

    public function testNormalRunPersistsEpisodeAndEpisodeTopics(): void
    {
        $this->profile('neko_nyan_balanced_radio', 'ねこにゃん');
        $this->bindPipeline();

        self::assertSame(0, Artisan::call('radiopipe:episodes:generate', [
            '--published-at' => '2026-05-28T07:00:00+09:00',
        ]));

        $episode = Episode::query()->with('topics')->firstOrFail();
        $firstTopic = $episode->topics->get(0);
        $secondTopic = $episode->topics->get(1);
        self::assertInstanceOf(EpisodeTopic::class, $firstTopic);
        self::assertInstanceOf(EpisodeTopic::class, $secondTopic);

        self::assertStringContainsString('Episode generated: id=', Artisan::output());
        self::assertSame('episode_2026-05-28_0700_neko_nyan_balanced_radio', $episode->episode_key);
        self::assertSame(Episode::STATUS_COMPLETED, $episode->status);
        self::assertCount(2, $episode->topics);
        self::assertSame('upstream:1', $firstTopic->topic_id);
        self::assertSame('used_in_scenario', $firstTopic->scenario_selection_status);
        self::assertSame('upstream:2', $secondTopic->topic_id);
        self::assertNull($secondTopic->scenario_selection_status);
    }

    public function testDuplicateEpisodeKeyFailsClearly(): void
    {
        $this->profile();
        $this->bindPipeline();
        Episode::query()->create([
            'episode_key' => 'episode_2026-05-28_0700_neko_nyan_balanced_radio',
            'date' => '2026-05-28',
            'processed_at' => '2026-05-28 07:00:00',
            'status' => Episode::STATUS_COMPLETED,
            'language' => 'ja',
            'scenario_json' => ['title' => 'existing'],
            'metadata' => [],
        ]);

        self::assertSame(1, Artisan::call('radiopipe:episodes:generate', [
            '--published-at' => '2026-05-28T07:00:00+09:00',
        ]));

        self::assertStringContainsString('already exists', Artisan::output());
        $this->assertDatabaseCount('episodes', 1);
    }

    public function testEditorialAnalyzerIsOnlyCalledForScreeningPassedTopicsAndScenarioGeneratorOnce(): void
    {
        $editorialAnalyzer = new EpisodeGenerateRecordingTopicEditorialAnalyzer();
        $scenarioGenerator = new EpisodeGenerateRecordingScenarioGenerator();
        $this->profile();
        $this->bindPipeline(editorialAnalyzer: $editorialAnalyzer, scenarioGenerator: $scenarioGenerator);

        self::assertSame(0, Artisan::call('radiopipe:episodes:generate'));

        self::assertSame(['upstream:1'], $editorialAnalyzer->topicIds);
        self::assertSame(1, $scenarioGenerator->callCount);
        self::assertNotNull($scenarioGenerator->lastInput);
        self::assertCount(1, $scenarioGenerator->lastInput->editorialEvaluations);
    }

    public function testItemLevelErrorsPersistCompletedWithErrorsWhenScenarioSucceeds(): void
    {
        $this->profile();
        $this->bindPipeline(editorialAnalyzer: new EpisodeGenerateFailingTopicEditorialAnalyzer());

        self::assertSame(0, Artisan::call('radiopipe:episodes:generate'));

        $episode = Episode::query()->firstOrFail();
        $errors = $episode->getAttribute('errors');
        self::assertSame(Episode::STATUS_COMPLETED_WITH_ERRORS, $episode->status);
        self::assertIsArray($errors);
        self::assertIsArray($errors[0]);
        self::assertSame('editorial failed', $errors[0]['message']);
    }

    public function testScenarioGenerationFailureDoesNotPersistEpisode(): void
    {
        $this->profile();
        $this->bindPipeline(scenarioGenerator: new EpisodeGenerateFailingScenarioGenerator());

        self::assertSame(1, Artisan::call('radiopipe:episodes:generate'));

        self::assertStringContainsString('Invalid scenario response.', Artisan::output());
        $this->assertDatabaseCount('episodes', 0);
    }

    public function testItDoesNotRequireRealExternalApis(): void
    {
        $this->profile();
        $upstream = new EpisodeGenerateRecordingUpstreamProvider([$this->upstreamItem(1)]);
        $this->bindPipeline(upstreamProvider: $upstream);

        self::assertSame(0, Artisan::call('radiopipe:episodes:generate'));

        self::assertNotNull($upstream->lastQuery);
        $this->assertDatabaseCount('episodes', 1);
    }

    private function bindPipeline(
        ?EpisodeGenerateRecordingUpstreamProvider $upstreamProvider = null,
        ?TopicEditorialAnalyzer $editorialAnalyzer = null,
        ?ScenarioGenerator $scenarioGenerator = null,
    ): void {
        $this->app->instance(UpstreamProvider::class, $upstreamProvider ?? new EpisodeGenerateRecordingUpstreamProvider([
            $this->upstreamItem(1),
            $this->upstreamItem(2),
        ]));
        $this->app->instance(TopicBuilder::class, new EpisodeGenerateFixtureTopicBuilder());
        $this->app->instance(TopicScreeningEvaluator::class, new EpisodeGenerateFixtureTopicScreeningEvaluator());
        $this->app->instance(TopicEditorialAnalyzer::class, $editorialAnalyzer ?? new EpisodeGenerateRecordingTopicEditorialAnalyzer());
        $this->app->instance(ScenarioTopicSelector::class, new ScenarioTopicSelector());
        $this->app->instance(ScenarioGenerator::class, $scenarioGenerator ?? new EpisodeGenerateRecordingScenarioGenerator());
    }

    private function profile(
        string $characterKey = 'neko_nyan_balanced_radio',
        string $name = 'ねこにゃん',
        bool $active = true,
    ): CharacterProfile {
        return CharacterProfile::factory()->create([
            'character_key' => $characterKey,
            'name' => $name,
            'is_active' => $active,
            'sort_order' => 10,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function jsonOutput(): array
    {
        $decoded = json_decode(trim(Artisan::output()), true);

        self::assertIsArray($decoded);

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }

    private function upstreamItem(int $id): UpstreamArticleItem
    {
        return new UpstreamArticleItem(
            upstreamId: $id,
            source: [
                'name' => 'Hacker News',
            ],
            article: [
                'title' => 'Topic ' . $id,
                'url' => 'https://example.test/articles/' . $id,
                'discussion_url' => 'https://news.ycombinator.com/item?id=' . $id,
                'published_at' => '2026-05-25T10:00:00Z',
                'fetched_at' => '2026-05-25T11:00:00Z',
            ],
            selection: [
                'status' => 'selected',
                'score' => 10,
            ],
            analysis: [
                'title' => [
                    'normalized' => 'Topic ' . $id,
                    'original' => 'Topic ' . $id,
                ],
                'content' => [
                    'brief' => 'Brief ' . $id,
                    'why_it_matters' => 'Why ' . $id,
                ],
                'classification' => [
                    'topics' => ['AI'],
                    'entities' => ['Example'],
                    'importance' => $id === 1 ? 4 : 1,
                    'confidence' => $id === 1 ? 0.95 : 0.9,
                    'content_type' => $id === 1 ? 'technical_article' : 'privacy_policy',
                ],
            ],
            processing: [
                'analyzed_at' => '2026-05-25T11:30:00Z',
            ],
            fetchedAt: CarbonImmutable::parse('2026-05-25T12:00:00Z'),
            providerName: 'fixture',
        );
    }
}

/**
 * @internal
 */
class EpisodeGenerateRecordingUpstreamProvider implements UpstreamProvider
{
    public ?UpstreamArticleQuery $lastQuery = null;

    /** @var list<UpstreamArticleItem> */
    private array $items;

    /**
     * Constructor.
     *
     * @param list<UpstreamArticleItem> $items
     */
    public function __construct(array $items)
    {
        $this->items = $items;
    }

    /**
     * @return list<UpstreamArticleItem>
     */
    public function fetch(UpstreamArticleQuery $query): array
    {
        $this->lastQuery = $query;

        return $this->items;
    }
}

/**
 * @internal
 */
class EpisodeGenerateFixtureTopicBuilder extends TopicBuilder
{
    public function build(UpstreamArticleItem $item): TopicDraft
    {
        return new TopicDraft(
            id: 'upstream:' . $item->upstreamId,
            sourceType: 'upstream',
            sourceName: 'Hacker News',
            title: 'Topic ' . $item->upstreamId,
            originalTitle: 'Topic ' . $item->upstreamId,
            url: 'https://example.test/articles/' . $item->upstreamId,
            discussionUrl: 'https://news.ycombinator.com/item?id=' . $item->upstreamId,
            summarySeed: 'Brief ' . $item->upstreamId,
            whyItMattersSeed: 'Why ' . $item->upstreamId,
            tags: ['AI'],
            entities: ['Example'],
            importance: 4,
            confidence: 0.95,
            contentType: 'technical_article',
            limitations: null,
            publishedAt: CarbonImmutable::parse('2026-05-25T10:00:00Z'),
            fetchedAt: CarbonImmutable::parse('2026-05-25T11:00:00Z'),
            sourceRefs: [
                'provider' => 'fixture',
                'upstream_id' => $item->upstreamId,
            ],
            upstreamSelection: [
                'status' => 'selected',
                'score' => 10,
            ],
        );
    }
}

/**
 * @internal
 */
class EpisodeGenerateFixtureTopicScreeningEvaluator extends TopicScreeningEvaluator
{
    public function evaluate(TopicDraft $draft, array $seenUrls = [], ?CarbonImmutable $now = null): TopicScreeningEvaluation
    {
        if ($draft->id === 'upstream:1') {
            return new TopicScreeningEvaluation(
                screeningStatus: TopicScreeningStatus::Passed,
                screeningScore: 88,
                signals: [],
                flags: [
                    'is_duplicate' => false,
                    'is_uncertain' => false,
                    'is_sensitive' => false,
                ],
                reasons: ['fixture_passed'],
            );
        }

        return new TopicScreeningEvaluation(
            screeningStatus: TopicScreeningStatus::RejectedLowValue,
            screeningScore: 30,
            signals: [],
            flags: [
                'is_duplicate' => false,
                'is_uncertain' => false,
                'is_sensitive' => false,
            ],
            reasons: ['fixture_rejected'],
        );
    }
}

/**
 * @internal
 */
class EpisodeGenerateRecordingTopicEditorialAnalyzer implements TopicEditorialAnalyzer
{
    /** @var list<string> */
    public array $topicIds = [];

    public function analyze(TopicDraft $topicDraft): TopicEditorialEvaluation
    {
        $this->topicIds[] = $topicDraft->id;

        return new TopicEditorialEvaluation(
            status: TopicEditorialStatus::Pending,
            editorialScore: 80,
            localized: new TopicLocalizedText(
                title: $topicDraft->title ?? '',
                summary: $topicDraft->summarySeed ?? '',
                whyItMatters: $topicDraft->whyItMattersSeed ?? '',
            ),
            scores: new TopicEditorialScores(80, 80, 80, 80, 80, 80),
            flags: new TopicEditorialFlags(false, false, false),
            duplicate: new TopicDuplicateAssessment(null, [], null, null, null),
            scenarioNotes: new TopicScenarioNotes('main_story', 'neutral', null, []),
            reasons: ['fixture_editorial'],
            metadata: [
                'driver' => 'fixture',
            ],
        );
    }
}

/**
 * @internal
 */
class EpisodeGenerateFailingTopicEditorialAnalyzer implements TopicEditorialAnalyzer
{
    public function analyze(TopicDraft $topicDraft): TopicEditorialEvaluation
    {
        throw new RuntimeException('editorial failed');
    }
}

/**
 * @internal
 */
class EpisodeGenerateRecordingScenarioGenerator implements ScenarioGenerator
{
    public int $callCount = 0;

    public ?ScenarioGenerationInput $lastInput = null;

    public function generate(ScenarioGenerationInput $input): ScenarioGenerationResult
    {
        ++$this->callCount;
        $this->lastInput = $input;

        return new ScenarioGenerationResult(
            scenario: new Scenario(
                title: '今日のギークニュース',
                language: 'ja',
                targetDurationSeconds: $input->targetDurationSeconds,
                estimatedDurationSeconds: 90,
                characterKey: $input->characterKey,
                scriptText: 'さてさて、今日のニュースです。',
                sections: [
                    new ScenarioSection(
                        type: 'topic',
                        title: 'Topic 1',
                        text: 'Topic 1 text',
                        topicIds: ['upstream:1'],
                        estimatedDurationSeconds: 90,
                    ),
                ],
                metadata: [
                    'driver' => 'fixture',
                    'schema_version' => '1.0',
                ],
            ),
            metadata: [
                'generator' => 'fixture',
            ],
        );
    }
}

/**
 * @internal
 */
class EpisodeGenerateFailingScenarioGenerator implements ScenarioGenerator
{
    public function generate(ScenarioGenerationInput $input): ScenarioGenerationResult
    {
        throw new RuntimeException('Invalid scenario response.');
    }
}
