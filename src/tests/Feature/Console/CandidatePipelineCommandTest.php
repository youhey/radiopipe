<?php

namespace Tests\Feature\Console;

use App\Models\CandidateTopic;
use App\Models\CharacterProfile;
use App\Models\Episode;
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
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * @internal
 */
class CandidatePipelineCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::clear();
        config([
            'radiopipe.episode.min_topics' => 1,
            'radiopipe.topic_nomination.throttle_seconds' => 0,
        ]);
    }

    public function testNominateCreatesCandidateTopicRecords(): void
    {
        $editorialAnalyzer = new CandidatePipelineRecordingTopicEditorialAnalyzer();
        $this->bindPipeline(editorialAnalyzer: $editorialAnalyzer);

        self::assertSame(0, Artisan::call('radiopipe:topics:nominate'));

        $candidate = CandidateTopic::query()->firstOrFail();
        self::assertSame('upstream:1', $candidate->topic_id);
        self::assertSame('passed', $candidate->screening_status);
        self::assertSame('pending', $candidate->editorial_status);
        self::assertNotSame('', $candidate->candidate_fingerprint);
        self::assertIsArray($candidate->getAttribute('topic_draft_json'));
        self::assertIsArray($candidate->getAttribute('screening_json'));
        self::assertIsArray($candidate->getAttribute('editorial_json'));
        self::assertSame(['upstream:1'], $editorialAnalyzer->topicIds);
    }

    public function testNominateThrottleSkipsRepeatedExecutionAndForceBypassesIt(): void
    {
        config(['radiopipe.topic_nomination.throttle_seconds' => 3600]);
        $upstreamProvider = new CandidatePipelineRecordingUpstreamProvider([$this->upstreamItem(1)]);
        $this->bindPipeline(upstreamProvider: $upstreamProvider);

        self::assertSame(0, Artisan::call('radiopipe:topics:nominate'));
        self::assertSame(1, $upstreamProvider->callCount);

        self::assertSame(0, Artisan::call('radiopipe:topics:nominate'));
        self::assertStringContainsString('Topic nomination throttle lock is active; skipped.', Artisan::output());
        self::assertSame(1, $upstreamProvider->callCount);

        self::assertSame(0, Artisan::call('radiopipe:topics:nominate', [
            '--force' => true,
        ]));
        self::assertSame(2, $upstreamProvider->callCount);
    }

    public function testEpisodesExportUsesCandidateTopicsAndDoesNotPersistEpisode(): void
    {
        $this->profile();
        $this->bindPipeline();
        self::assertSame(0, Artisan::call('radiopipe:topics:nominate'));

        self::assertSame(0, Artisan::call('radiopipe:episodes:export'));

        $output = json_decode(trim(Artisan::output()), true);
        self::assertIsArray($output);
        self::assertSame('1.0', $output['schema_version']);
        self::assertIsArray($output['scenario']);
        $character = $output['character'] ?? null;
        self::assertIsArray($character);
        self::assertSame('neko_nyan_balanced_radio', $character['character_key']);
        $this->assertDatabaseCount('episodes', 0);
    }

    public function testEpisodesCompileCreatesEpisodeAndSkipsNextRunWhenUsedCandidatesAreExcluded(): void
    {
        $this->profile();
        $this->bindPipeline();
        self::assertSame(0, Artisan::call('radiopipe:topics:nominate'));

        self::assertSame(0, Artisan::call('radiopipe:episodes:compile'));
        self::assertStringContainsString('Episode compiled: id=', Artisan::output());
        $this->assertDatabaseCount('episodes', 1);

        self::assertSame(0, Artisan::call('radiopipe:episodes:compile'));
        self::assertStringContainsString('Skipped episode compile: not enough candidate topics. required=1 actual=0', Artisan::output());
        $this->assertDatabaseCount('episodes', 1);
    }

    public function testEpisodesCompileSkipsWithoutScenarioGenerationWhenCandidateTopicsAreInsufficient(): void
    {
        config(['radiopipe.episode.min_topics' => 5]);
        $scenarioGenerator = new CandidatePipelineRecordingScenarioGenerator();
        $this->profile();
        $this->bindPipeline(
            upstreamProvider: new CandidatePipelineRecordingUpstreamProvider([
                $this->upstreamItem(1),
                $this->upstreamItem(2),
                $this->upstreamItem(3),
            ]),
            scenarioGenerator: $scenarioGenerator,
        );

        self::assertSame(0, Artisan::call('radiopipe:topics:nominate'));
        $this->assertDatabaseCount('candidate_topics', 3);

        self::assertSame(0, Artisan::call('radiopipe:episodes:compile'));

        self::assertStringContainsString('Skipped episode compile: not enough candidate topics. required=5 actual=3', Artisan::output());
        self::assertSame(0, $scenarioGenerator->callCount);
        $this->assertDatabaseCount('episodes', 0);
        $this->assertDatabaseCount('episode_topics', 0);
    }

    public function testEpisodesCompileUsesConfiguredMinimumCandidateTopicCount(): void
    {
        config(['radiopipe.episode.min_topics' => 3]);
        $scenarioGenerator = new CandidatePipelineRecordingScenarioGenerator();
        $this->profile();
        $this->bindPipeline(
            upstreamProvider: new CandidatePipelineRecordingUpstreamProvider([
                $this->upstreamItem(1),
                $this->upstreamItem(2),
                $this->upstreamItem(3),
            ]),
            scenarioGenerator: $scenarioGenerator,
        );

        self::assertSame(0, Artisan::call('radiopipe:topics:nominate'));
        self::assertSame(0, Artisan::call('radiopipe:episodes:compile'));

        self::assertStringContainsString('Episode compiled: id=', Artisan::output());
        self::assertSame(1, $scenarioGenerator->callCount);
        $this->assertDatabaseCount('episodes', 1);
    }

    public function testEpisodesCompileExcludesPreviouslyUsedUpstreamArticles(): void
    {
        config(['radiopipe.episode.min_topics' => 2]);
        $scenarioGenerator = new CandidatePipelineRecordingScenarioGenerator();
        $this->profile();
        $this->bindPipeline(
            upstreamProvider: new CandidatePipelineRecordingUpstreamProvider([
                $this->upstreamItem(1),
                $this->upstreamItem(2),
                $this->upstreamItem(3),
            ]),
            scenarioGenerator: $scenarioGenerator,
        );

        self::assertSame(0, Artisan::call('radiopipe:topics:nominate'));
        $this->usedEpisodeTopic($this->episode(), [
            'topic_id' => 'old-topic',
            'upstream_provider' => 'fixture',
            'upstream_id' => '1',
        ]);

        self::assertSame(0, Artisan::call('radiopipe:episodes:compile'));

        self::assertSame(1, $scenarioGenerator->callCount);
        $topicIds = $this->latestCompiledTopicIds();
        self::assertNotContains('upstream:1', $topicIds);
        self::assertContains('upstream:2', $topicIds);
        self::assertContains('upstream:3', $topicIds);
    }

    public function testEpisodesCompileExcludesPreviouslyUsedTopicIds(): void
    {
        config(['radiopipe.episode.min_topics' => 2]);
        $this->profile();
        $this->bindPipeline(
            upstreamProvider: new CandidatePipelineRecordingUpstreamProvider([
                $this->upstreamItem(1),
                $this->upstreamItem(2),
                $this->upstreamItem(3),
            ]),
        );

        self::assertSame(0, Artisan::call('radiopipe:topics:nominate'));
        $candidate = CandidateTopic::query()->where('topic_id', 'upstream:1')->firstOrFail();
        $candidateId = $candidate->getKey();
        self::assertIsInt($candidateId);
        $this->usedEpisodeTopic($this->episode(), [
            'topic_id' => (string) $candidateId,
            'upstream_provider' => 'different',
            'upstream_id' => 'different',
        ]);

        self::assertSame(0, Artisan::call('radiopipe:episodes:compile'));

        $topicIds = $this->latestCompiledTopicIds();
        self::assertNotContains('upstream:1', $topicIds);
        self::assertContains('upstream:2', $topicIds);
        self::assertContains('upstream:3', $topicIds);
    }

    public function testEpisodesCompileDoesNotExcludeSelectedNotUsedTopics(): void
    {
        $this->profile();
        $this->bindPipeline(
            upstreamProvider: new CandidatePipelineRecordingUpstreamProvider([
                $this->upstreamItem(1),
                $this->upstreamItem(2),
            ]),
        );

        self::assertSame(0, Artisan::call('radiopipe:topics:nominate'));
        $this->usedEpisodeTopic($this->episode(), [
            'topic_id' => 'upstream:1',
            'upstream_provider' => 'fixture',
            'upstream_id' => '1',
            'scenario_selection_status' => 'selected_not_used',
        ]);

        self::assertSame(0, Artisan::call('radiopipe:episodes:compile'));

        self::assertContains('upstream:1', $this->latestCompiledTopicIds());
    }

    public function testEpisodesCompileDoesNotExcludeTopicsFromFailedEpisodes(): void
    {
        $this->profile();
        $this->bindPipeline(
            upstreamProvider: new CandidatePipelineRecordingUpstreamProvider([
                $this->upstreamItem(1),
                $this->upstreamItem(2),
            ]),
        );

        self::assertSame(0, Artisan::call('radiopipe:topics:nominate'));
        $this->usedEpisodeTopic($this->episode(Episode::STATUS_FAILED), [
            'topic_id' => 'upstream:1',
            'upstream_provider' => 'fixture',
            'upstream_id' => '1',
        ]);

        self::assertSame(0, Artisan::call('radiopipe:episodes:compile'));

        self::assertContains('upstream:1', $this->latestCompiledTopicIds());
    }

    public function testEpisodesCompileDoesNotExcludeCandidatesByNullUpstreamIdsOnly(): void
    {
        $this->profile();
        $this->bindPipeline(
            upstreamProvider: new CandidatePipelineRecordingUpstreamProvider([
                $this->upstreamItem(1),
                $this->upstreamItem(2),
            ]),
        );

        self::assertSame(0, Artisan::call('radiopipe:topics:nominate'));
        $candidate = CandidateTopic::query()->where('topic_id', 'upstream:1')->firstOrFail();
        $draft = $candidate->getAttribute('topic_draft_json');
        self::assertIsArray($draft);
        $draft['source_refs'] = [
            'provider' => 'fixture',
            'upstream_id' => null,
        ];
        $candidate->update([
            'upstream_id' => null,
            'topic_draft_json' => $draft,
        ]);
        $this->usedEpisodeTopic($this->episode(), [
            'topic_id' => 'different-topic',
            'upstream_provider' => 'fixture',
            'upstream_id' => null,
        ]);

        self::assertSame(0, Artisan::call('radiopipe:episodes:compile'));

        self::assertContains('upstream:1', $this->latestCompiledTopicIds());
    }

    private function bindPipeline(
        ?CandidatePipelineRecordingUpstreamProvider $upstreamProvider = null,
        ?TopicEditorialAnalyzer $editorialAnalyzer = null,
        ?ScenarioGenerator $scenarioGenerator = null,
    ): void {
        $this->app->instance(UpstreamProvider::class, $upstreamProvider ?? new CandidatePipelineRecordingUpstreamProvider([
            $this->upstreamItem(1),
        ]));
        $this->app->instance(TopicBuilder::class, new CandidatePipelineFixtureTopicBuilder());
        $this->app->instance(TopicScreeningEvaluator::class, new CandidatePipelineFixtureTopicScreeningEvaluator());
        $this->app->instance(TopicEditorialAnalyzer::class, $editorialAnalyzer ?? new CandidatePipelineRecordingTopicEditorialAnalyzer());
        $this->app->instance(ScenarioTopicSelector::class, new ScenarioTopicSelector());
        $this->app->instance(ScenarioGenerator::class, $scenarioGenerator ?? new CandidatePipelineRecordingScenarioGenerator());
    }

    private function profile(): CharacterProfile
    {
        return CharacterProfile::factory()->create([
            'character_key' => 'neko_nyan_balanced_radio',
            'name' => 'ねこにゃん',
            'is_active' => true,
            'sort_order' => 10,
        ]);
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
                    'importance' => 4,
                    'confidence' => 0.95,
                    'content_type' => 'technical_article',
                ],
            ],
            processing: [
                'analyzed_at' => '2026-05-25T11:30:00Z',
            ],
            fetchedAt: CarbonImmutable::parse('2026-05-25T12:00:00Z'),
            providerName: 'fixture',
        );
    }

    private function episode(string $status = Episode::STATUS_COMPLETED): Episode
    {
        return Episode::query()->create([
            'episode_key' => 'episode_' . str_replace('.', '_', uniqid('', true)),
            'date' => CarbonImmutable::parse('2026-05-25T00:00:00Z'),
            'published_at' => CarbonImmutable::parse('2026-05-25T09:00:00Z'),
            'processed_at' => CarbonImmutable::parse('2026-05-25T09:00:00Z'),
            'character_key' => 'neko_nyan_balanced_radio',
            'status' => $status,
            'title' => '過去の Episode',
            'language' => 'ja',
            'target_duration_seconds' => 900,
            'estimated_duration_seconds' => 90,
            'scenario_json' => [
                'title' => '過去の Episode',
                'sections' => [],
            ],
            'metadata' => [],
        ]);
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function usedEpisodeTopic(Episode $episode, array $overrides = []): void
    {
        $episode->topics()->create(array_merge([
            'topic_id' => 'upstream:1',
            'upstream_provider' => 'fixture',
            'upstream_id' => '1',
            'source_name' => 'Hacker News',
            'source_type' => 'upstream',
            'title' => 'Topic 1',
            'url' => 'https://example.test/articles/1',
            'screening_status' => 'passed',
            'editorial_status' => 'pending',
            'scenario_selection_status' => 'used_in_scenario',
            'sort_order' => 1,
            'topic_draft_json' => [],
            'screening_json' => [],
            'editorial_json' => [],
            'scenario_selection_json' => [
                'status' => 'used_in_scenario',
            ],
            'metadata' => [],
        ], $overrides));
    }

    /**
     * @return list<string>
     */
    private function latestCompiledTopicIds(): array
    {
        $episode = Episode::query()
            ->where('episode_key', 'like', 'episode_2026-%')
            ->latest('id')
            ->firstOrFail();
        $episodeId = $episode->getKey();
        self::assertIsInt($episodeId);

        /** @var list<string> $topicIds */
        $topicIds = DB::table('episode_topics')
            ->where('episode_id', $episodeId)
            ->orderBy('id')
            ->pluck('topic_id')
            ->filter(static fn (mixed $topicId): bool => is_string($topicId))
            ->values()
            ->all();

        return $topicIds;
    }
}

/**
 * @internal
 */
class CandidatePipelineRecordingUpstreamProvider implements UpstreamProvider
{
    public int $callCount = 0;

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
        ++$this->callCount;

        return $this->items;
    }
}

/**
 * @internal
 */
class CandidatePipelineFixtureTopicBuilder extends TopicBuilder
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
class CandidatePipelineFixtureTopicScreeningEvaluator extends TopicScreeningEvaluator
{
    public function evaluate(TopicDraft $draft, array $seenUrls = [], ?CarbonImmutable $now = null): TopicScreeningEvaluation
    {
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
}

/**
 * @internal
 */
class CandidatePipelineRecordingTopicEditorialAnalyzer implements TopicEditorialAnalyzer
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
class CandidatePipelineRecordingScenarioGenerator implements ScenarioGenerator
{
    public int $callCount = 0;

    public function generate(ScenarioGenerationInput $input): ScenarioGenerationResult
    {
        ++$this->callCount;

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
