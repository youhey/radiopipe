<?php

namespace Tests\Unit\Console;

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
use Illuminate\Console\Scheduling\CallbackEvent;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * @internal
 */
class PipelineScheduleTest extends TestCase
{
    use RefreshDatabase;

    public function testPipelineCompileCallbackIsScheduledEveryTenMinutes(): void
    {
        $event = $this->pipelineEvent();

        self::assertSame('*/10 * * * *', $event->expression);
        self::assertTrue($event->withoutOverlapping);
        self::assertSame(30, $event->expiresAt);
        self::assertSame('radiopipe:pipeline:compile', $event->description);
    }

    public function testScheduledPipelineCallbackRunsNominationThenCompilation(): void
    {
        CharacterProfile::factory()->create([
            'character_key' => 'neko_nyan_balanced_radio',
            'name' => 'ねこにゃん',
            'is_active' => true,
            'sort_order' => 10,
        ]);
        $this->app->instance(UpstreamProvider::class, new PipelineScheduleRecordingUpstreamProvider([
            $this->upstreamItem(1),
        ]));
        $this->app->instance(TopicBuilder::class, new PipelineScheduleFixtureTopicBuilder());
        $this->app->instance(TopicScreeningEvaluator::class, new PipelineScheduleFixtureTopicScreeningEvaluator());
        $this->app->instance(TopicEditorialAnalyzer::class, new PipelineScheduleRecordingTopicEditorialAnalyzer());
        $this->app->instance(ScenarioTopicSelector::class, new ScenarioTopicSelector());
        $this->app->instance(ScenarioGenerator::class, new PipelineScheduleRecordingScenarioGenerator());

        $event = $this->pipelineEvent();

        self::assertSame(0, $event->run(app()));
        $this->assertDatabaseCount('candidate_topics', 1);
        $this->assertDatabaseCount('episodes', 1);
    }

    /**
     * schedule に登録された pipeline event を取り出します。
     */
    private function pipelineEvent(): CallbackEvent
    {
        $events = app(Schedule::class)->events();

        foreach ($events as $event) {
            if ($event instanceof CallbackEvent && $event->description === 'radiopipe:pipeline:compile') {
                return $event;
            }
        }

        self::fail('radiopipe:pipeline:compile was not scheduled.');
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
}

/**
 * @internal
 */
class PipelineScheduleRecordingUpstreamProvider implements UpstreamProvider
{
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
        return $this->items;
    }
}

/**
 * @internal
 */
class PipelineScheduleFixtureTopicBuilder extends TopicBuilder
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
class PipelineScheduleFixtureTopicScreeningEvaluator extends TopicScreeningEvaluator
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
class PipelineScheduleRecordingTopicEditorialAnalyzer implements TopicEditorialAnalyzer
{
    public function analyze(TopicDraft $topicDraft): TopicEditorialEvaluation
    {
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
class PipelineScheduleRecordingScenarioGenerator implements ScenarioGenerator
{
    public function generate(ScenarioGenerationInput $input): ScenarioGenerationResult
    {
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
