<?php

namespace Tests\Feature\Console;

use App\Models\CharacterProfile;
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
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;
use Tests\TestCase;

/**
 * @internal
 */
class ScenarioDebugCommandTest extends TestCase
{
    use RefreshDatabase;

    public function testCommandExists(): void
    {
        $this->profile();
        $this->bindPipeline();

        self::assertSame(0, Artisan::call('radiopipe:scenario:debug', [
            '--help' => true,
        ]));
    }

    public function testItUsesDefaultFromToLimitAndFirstActiveCharacterWhenOmitted(): void
    {
        config(['app.timezone' => 'Asia/Tokyo']);
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-05-25T12:34:56+09:00'));
        $this->profile('later_character', 'あと', sortOrder: 20);
        $this->profile('first_character', '先', sortOrder: 10);
        $upstream = new ScenarioRecordingUpstreamProvider([$this->upstreamItem(1)]);
        $this->bindPipeline(upstreamProvider: $upstream);

        Artisan::call('radiopipe:scenario:debug');
        $output = $this->jsonOutput();
        $input = $this->arrayValue($output, 'input');
        $character = $this->arrayValue($output, 'character');

        self::assertSame('2026-05-24T12:34:56+09:00', $input['from']);
        self::assertSame('2026-05-25T12:34:56+09:00', $input['to']);
        self::assertSame(20, $input['limit']);
        self::assertSame('first_character', $input['character_key']);
        self::assertSame('first_character', $character['character_key']);
        self::assertNotNull($upstream->lastQuery);
        self::assertSame(20, $upstream->lastQuery->limit);

        CarbonImmutable::setTestNow();
    }

    public function testItAcceptsExplicitOptions(): void
    {
        $this->profile('explicit_character', '明示');
        $upstream = new ScenarioRecordingUpstreamProvider([$this->upstreamItem(1)]);
        $this->bindPipeline(upstreamProvider: $upstream);

        Artisan::call('radiopipe:scenario:debug', [
            '--from' => '2026-05-23T10:00:00+09:00',
            '--to' => '2026-05-24T10:00:00+09:00',
            '--limit' => '3',
            '--character' => 'explicit_character',
        ]);
        $output = $this->jsonOutput();
        $input = $this->arrayValue($output, 'input');

        self::assertSame('2026-05-23T10:00:00+09:00', $input['from']);
        self::assertSame('2026-05-24T10:00:00+09:00', $input['to']);
        self::assertSame(3, $input['limit']);
        self::assertSame('explicit_character', $input['character_key']);
        self::assertNotNull($upstream->lastQuery);
        self::assertSame(3, $upstream->lastQuery->limit);
    }

    public function testItFailsClearlyWhenNoActiveCharacterProfileExists(): void
    {
        $this->bindPipeline();

        self::assertSame(1, Artisan::call('radiopipe:scenario:debug'));
        self::assertStringContainsString('No active character profile was found.', Artisan::output());
    }

    public function testItFailsClearlyWhenSpecifiedCharacterIsMissingOrInactive(): void
    {
        $this->profile('inactive_character', '非活性', active: false);
        $this->bindPipeline();

        self::assertSame(1, Artisan::call('radiopipe:scenario:debug', [
            '--character' => 'inactive_character',
        ]));
        self::assertStringContainsString('Active character profile [inactive_character] was not found.', Artisan::output());
    }

    public function testItOutputsValidJsonWithExpectedTopLevelKeys(): void
    {
        $this->profile();
        $this->bindPipeline();

        Artisan::call('radiopipe:scenario:debug');
        $output = $this->jsonOutput();

        self::assertSame([
            'schema_version',
            'generated_at',
            'input',
            'character',
            'counts',
            'items',
            'scenario',
            'errors',
        ], array_keys($output));
        self::assertSame('1.0', $output['schema_version']);
        self::assertSame([], $output['errors']);
        self::assertIsArray($output['scenario']);
    }

    public function testRejectedScreeningItemsRemainWithNullEditorialAndSelection(): void
    {
        $editorialAnalyzer = new ScenarioRecordingTopicEditorialAnalyzer();
        $this->profile();
        $this->bindPipeline(editorialAnalyzer: $editorialAnalyzer);

        Artisan::call('radiopipe:scenario:debug');
        $output = $this->jsonOutput();
        $items = $this->listValue($output, 'items');
        $first = $this->arrayValue($items, 0);
        $second = $this->arrayValue($items, 1);
        $firstScreening = $this->arrayValue($first, 'screening');
        $secondScreening = $this->arrayValue($second, 'screening');

        self::assertSame('passed', $firstScreening['screening_status']);
        self::assertIsArray($first['editorial']);
        self::assertIsArray($first['selection']);
        self::assertSame('rejected_low_value', $secondScreening['screening_status']);
        self::assertNull($second['editorial']);
        self::assertNull($second['selection']);
        self::assertSame(['upstream:1'], $editorialAnalyzer->topicIds);
    }

    public function testScenarioGeneratorIsCalledOnce(): void
    {
        $scenarioGenerator = new ScenarioRecordingScenarioGenerator();
        $this->profile();
        $this->bindPipeline(scenarioGenerator: $scenarioGenerator);

        Artisan::call('radiopipe:scenario:debug');

        self::assertSame(1, $scenarioGenerator->callCount);
        self::assertNotNull($scenarioGenerator->lastInput);
        self::assertSame('neko_nyan_balanced_radio', $scenarioGenerator->lastInput->characterKey);
        self::assertCount(1, $scenarioGenerator->lastInput->editorialEvaluations);
    }

    public function testScenarioGenerationFailureRecordsErrorAndReturnsFailure(): void
    {
        $this->profile();
        $this->bindPipeline(scenarioGenerator: new ScenarioFailingScenarioGenerator());

        self::assertSame(1, Artisan::call('radiopipe:scenario:debug'));

        $output = $this->jsonOutput();
        $errors = $this->listValue($output, 'errors');
        $error = $this->arrayValue($errors, 0);

        self::assertNull($output['scenario']);
        self::assertSame('scenario', $error['stage']);
        self::assertSame('Invalid scenario response.', $error['message']);
    }

    public function testItDoesNotWriteFiles(): void
    {
        $before = $this->trackedFileList();
        $this->profile();
        $this->bindPipeline();

        Artisan::call('radiopipe:scenario:debug');

        self::assertSame($before, $this->trackedFileList());
    }

    private function bindPipeline(
        ?ScenarioRecordingUpstreamProvider $upstreamProvider = null,
        ?TopicEditorialAnalyzer $editorialAnalyzer = null,
        ?ScenarioGenerator $scenarioGenerator = null,
    ): void {
        $this->app->instance(UpstreamProvider::class, $upstreamProvider ?? new ScenarioRecordingUpstreamProvider([
            $this->upstreamItem(1),
            $this->upstreamItem(2),
        ]));
        $this->app->instance(TopicBuilder::class, new ScenarioFixtureTopicBuilder());
        $this->app->instance(TopicScreeningEvaluator::class, new ScenarioFixtureTopicScreeningEvaluator());
        $this->app->instance(TopicEditorialAnalyzer::class, $editorialAnalyzer ?? new ScenarioRecordingTopicEditorialAnalyzer());
        $this->app->instance(ScenarioTopicSelector::class, new ScenarioTopicSelector());
        $this->app->instance(ScenarioGenerator::class, $scenarioGenerator ?? new ScenarioRecordingScenarioGenerator());
    }

    private function profile(
        string $characterKey = 'neko_nyan_balanced_radio',
        string $name = 'ねこにゃん',
        bool $active = true,
        int $sortOrder = 10,
    ): CharacterProfile {
        return CharacterProfile::factory()->create([
            'character_key' => $characterKey,
            'name' => $name,
            'is_active' => $active,
            'sort_order' => $sortOrder,
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

    /**
     * @param array<string, mixed>|list<mixed> $payload
     *
     * @return array<string, mixed>
     */
    private function arrayValue(array $payload, int|string $key): array
    {
        $value = $payload[$key] ?? null;

        self::assertIsArray($value);

        /** @var array<string, mixed> $value */
        return $value;
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return list<mixed>
     */
    private function listValue(array $payload, string $key): array
    {
        $value = $payload[$key] ?? null;

        self::assertIsArray($value);

        return array_values($value);
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

    /**
     * @return list<string>
     */
    private function trackedFileList(): array
    {
        $paths = [
            storage_path('app'),
        ];
        $files = [];

        foreach ($paths as $path) {
            if (! is_dir($path)) {
                continue;
            }

            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path));

            foreach ($iterator as $file) {
                if (! $file instanceof SplFileInfo) {
                    continue;
                }

                if ($file->isFile()) {
                    $files[] = $file->getPathname();
                }
            }
        }

        sort($files);

        return $files;
    }
}

/**
 * @internal
 */
class ScenarioRecordingUpstreamProvider implements UpstreamProvider
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
class ScenarioFixtureTopicBuilder extends TopicBuilder
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
class ScenarioFixtureTopicScreeningEvaluator extends TopicScreeningEvaluator
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
class ScenarioRecordingTopicEditorialAnalyzer implements TopicEditorialAnalyzer
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
class ScenarioRecordingScenarioGenerator implements ScenarioGenerator
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
                        type: 'opening',
                        title: 'オープニング',
                        text: 'さてさて、今日のニュースです。',
                        topicIds: [],
                        estimatedDurationSeconds: 30,
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
class ScenarioFailingScenarioGenerator implements ScenarioGenerator
{
    public function generate(ScenarioGenerationInput $input): ScenarioGenerationResult
    {
        throw new RuntimeException('Invalid scenario response.');
    }
}
