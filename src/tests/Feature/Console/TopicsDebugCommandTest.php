<?php

namespace Tests\Feature\Console;

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
use App\Topics\TopicDraft;
use App\Upstream\UpstreamArticleItem;
use App\Upstream\UpstreamArticleQuery;
use App\Upstream\UpstreamProvider;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Artisan;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;
use Tests\TestCase;

/**
 * @internal
 */
class TopicsDebugCommandTest extends TestCase
{
    public function testCommandExists(): void
    {
        $this->bindPipeline();

        self::assertSame(0, Artisan::call('radiopipe:topics:debug', [
            '--help' => true,
        ]));
    }

    public function testItUsesDefaultFromToAndLimitValuesWhenOmitted(): void
    {
        config(['app.timezone' => 'Asia/Tokyo']);
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-05-25T12:34:56+09:00'));
        $upstream = new RecordingUpstreamProvider([$this->upstreamItem(1)]);
        $this->bindPipeline(upstreamProvider: $upstream);

        Artisan::call('radiopipe:topics:debug');
        $output = $this->jsonOutput();
        $input = $this->arrayValue($output, 'input');

        self::assertSame('2026-05-24T12:34:56+09:00', $input['from']);
        self::assertSame('2026-05-25T12:34:56+09:00', $input['to']);
        self::assertSame(20, $input['limit']);
        self::assertNotNull($upstream->lastQuery);
        self::assertSame(20, $upstream->lastQuery->limit);

        CarbonImmutable::setTestNow();
    }

    public function testItAcceptsExplicitFromToAndLimit(): void
    {
        $upstream = new RecordingUpstreamProvider([$this->upstreamItem(1)]);
        $this->bindPipeline(upstreamProvider: $upstream);

        Artisan::call('radiopipe:topics:debug', [
            '--from' => '2026-05-23T10:00:00+09:00',
            '--to' => '2026-05-24T10:00:00+09:00',
            '--limit' => '3',
        ]);
        $output = $this->jsonOutput();
        $input = $this->arrayValue($output, 'input');

        self::assertSame('2026-05-23T10:00:00+09:00', $input['from']);
        self::assertSame('2026-05-24T10:00:00+09:00', $input['to']);
        self::assertSame(3, $input['limit']);
        self::assertNotNull($upstream->lastQuery);
        self::assertSame(3, $upstream->lastQuery->limit);
    }

    public function testItOutputsValidJsonWithExpectedTopLevelKeys(): void
    {
        $this->bindPipeline();

        Artisan::call('radiopipe:topics:debug');
        $output = $this->jsonOutput();

        self::assertSame([
            'schema_version',
            'generated_at',
            'input',
            'count',
            'items',
            'errors',
        ], array_keys($output));
        self::assertSame('1.0', $output['schema_version']);
        self::assertSame(2, $output['count']);
        self::assertSame([], $output['errors']);
    }

    public function testRejectedScreeningItemsRemainWithNullEditorial(): void
    {
        $editorialAnalyzer = new RecordingTopicEditorialAnalyzer();
        $this->bindPipeline(editorialAnalyzer: $editorialAnalyzer);

        Artisan::call('radiopipe:topics:debug');
        $output = $this->jsonOutput();
        $items = $this->listValue($output, 'items');
        $first = $this->arrayValue($items, 0);
        $second = $this->arrayValue($items, 1);
        $firstScreening = $this->arrayValue($first, 'screening');
        $secondScreening = $this->arrayValue($second, 'screening');

        self::assertSame('passed', $firstScreening['screening_status']);
        self::assertIsArray($first['editorial']);
        self::assertSame('rejected_low_value', $secondScreening['screening_status']);
        self::assertNull($second['editorial']);
    }

    public function testEditorialAnalyzerIsOnlyCalledForScreeningPassedTopics(): void
    {
        $editorialAnalyzer = new RecordingTopicEditorialAnalyzer();
        $this->bindPipeline(editorialAnalyzer: $editorialAnalyzer);

        Artisan::call('radiopipe:topics:debug');

        self::assertSame(['upstream:1'], $editorialAnalyzer->topicIds);
    }

    public function testEditorialAnalyzerFailureKeepsEditorialNullAndRecordsError(): void
    {
        $this->bindPipeline(editorialAnalyzer: new FailingTopicEditorialAnalyzer());

        self::assertSame(1, Artisan::call('radiopipe:topics:debug'));

        $output = $this->jsonOutput();
        $items = $this->listValue($output, 'items');
        $first = $this->arrayValue($items, 0);
        $errors = $this->listValue($output, 'errors');
        $error = $this->arrayValue($errors, 0);

        self::assertNull($first['editorial']);
        self::assertSame('editorial', $error['stage']);
        self::assertSame('upstream:1', $error['topic_id']);
        self::assertSame('score range validation failed', $error['message']);
    }

    public function testItDoesNotWriteFiles(): void
    {
        $before = $this->trackedFileList();
        $this->bindPipeline();

        Artisan::call('radiopipe:topics:debug');

        self::assertSame($before, $this->trackedFileList());
    }

    /**
     * @param list<UpstreamArticleItem>|null $items
     */
    private function bindPipeline(?RecordingUpstreamProvider $upstreamProvider = null, ?TopicEditorialAnalyzer $editorialAnalyzer = null, ?array $items = null): void
    {
        $this->app->instance(UpstreamProvider::class, $upstreamProvider ?? new RecordingUpstreamProvider($items ?? [
            $this->upstreamItem(1),
            $this->upstreamItem(2),
        ]));
        $this->app->instance(TopicScreeningEvaluator::class, new FixtureTopicScreeningEvaluator());
        $this->app->instance(TopicEditorialAnalyzer::class, $editorialAnalyzer ?? new RecordingTopicEditorialAnalyzer());
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
class RecordingUpstreamProvider implements UpstreamProvider
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
class FixtureTopicScreeningEvaluator extends TopicScreeningEvaluator
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
class RecordingTopicEditorialAnalyzer implements TopicEditorialAnalyzer
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
            scores: new TopicEditorialScores(
                preference: 80,
                generalImportance: 80,
                freshness: 80,
                certainty: 80,
                scenarioFitness: 80,
                flowFlexibility: 80,
            ),
            flags: new TopicEditorialFlags(
                isDuplicateCandidate: false,
                isUncertain: false,
                isSensitive: false,
            ),
            duplicate: new TopicDuplicateAssessment(
                canonicalKey: null,
                similarTopicIds: [],
                duplicateOf: null,
                confidence: null,
                reason: null,
            ),
            scenarioNotes: new TopicScenarioNotes(
                suggestedRole: 'main_story',
                tone: 'neutral',
                transitionHint: null,
                avoid: [],
            ),
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
class FailingTopicEditorialAnalyzer implements TopicEditorialAnalyzer
{
    public function analyze(TopicDraft $topicDraft): TopicEditorialEvaluation
    {
        throw new RuntimeException('score range validation failed');
    }
}
