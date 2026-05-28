<?php

namespace Tests\Feature\Episodes;

use App\Episodes\EpisodeRecorder;
use App\Episodes\EpisodeRecordInput;
use App\Models\CharacterProfile;
use App\Models\Episode;
use App\Models\EpisodeTopic;
use App\Scenarios\Scenario;
use App\Scenarios\ScenarioGenerationResult;
use App\Scenarios\ScenarioSection;
use App\Scenarios\ScenarioTopicSelection;
use App\Scenarios\ScenarioTopicSelectionStatus;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * @internal
 */
class EpisodeRecorderTest extends TestCase
{
    use RefreshDatabase;

    public function testEpisodeCanBeCreatedWithScenarioJsonAndTopicRows(): void
    {
        $characterProfile = CharacterProfile::factory()->create();

        $episode = Episode::query()->create([
            'episode_key' => 'episode_2026-05-28_0900_dummy',
            'date' => '2026-05-28',
            'processed_at' => '2026-05-28 09:00:00',
            'character_profile_id' => $characterProfile->id,
            'character_key' => $characterProfile->character_key,
            'status' => Episode::STATUS_COMPLETED,
            'title' => '今日のギークニュース',
            'language' => 'ja',
            'target_duration_seconds' => 900,
            'estimated_duration_seconds' => 120,
            'scenario_json' => ['title' => '今日のギークニュース', 'sections' => []],
            'metadata' => ['generator' => 'fixture'],
            'errors' => null,
        ]);
        $episode->topics()->create([
            'topic_id' => 'upstream:1',
            'sort_order' => 1,
            'topic_draft_json' => ['id' => 'upstream:1'],
            'metadata' => ['source' => 'fixture'],
        ]);

        $freshEpisode = Episode::query()->with('topics')->findOrFail($episode->id);
        $scenarioJson = $freshEpisode->getAttribute('scenario_json');
        $metadata = $freshEpisode->getAttribute('metadata');
        $firstTopic = $freshEpisode->topics->first();
        self::assertIsArray($scenarioJson);
        self::assertIsArray($metadata);
        self::assertInstanceOf(EpisodeTopic::class, $firstTopic);

        self::assertSame('今日のギークニュース', $scenarioJson['title']);
        self::assertSame('fixture', $metadata['generator']);
        self::assertNull($freshEpisode->errors);
        self::assertSame(900, $freshEpisode->target_duration_seconds);
        self::assertInstanceOf(CarbonInterface::class, $freshEpisode->date);
        self::assertSame('2026-05-28', $freshEpisode->date->toDateString());
        self::assertCount(1, $freshEpisode->topics);
        self::assertSame(['id' => 'upstream:1'], $firstTopic->topic_draft_json);
        self::assertSame($characterProfile->id, $freshEpisode->characterProfile?->id);
    }

    public function testEpisodeTopicJsonCastsWork(): void
    {
        $episode = $this->episode();

        $topic = EpisodeTopic::query()->create([
            'episode_id' => $episode->id,
            'topic_id' => 'upstream:2',
            'sort_order' => 2,
            'topic_draft_json' => ['id' => 'upstream:2'],
            'screening_json' => ['screening_status' => 'passed'],
            'editorial_json' => ['status' => 'pending'],
            'scenario_selection_json' => ['status' => 'used_in_scenario'],
            'metadata' => ['note' => 'fixture'],
        ]);

        $freshTopic = EpisodeTopic::query()->findOrFail($topic->id);
        $screeningJson = $freshTopic->getAttribute('screening_json');
        $editorialJson = $freshTopic->getAttribute('editorial_json');
        $selectionJson = $freshTopic->getAttribute('scenario_selection_json');
        $metadata = $freshTopic->getAttribute('metadata');
        self::assertIsArray($screeningJson);
        self::assertIsArray($editorialJson);
        self::assertIsArray($selectionJson);
        self::assertIsArray($metadata);

        self::assertSame(2, $freshTopic->sort_order);
        self::assertSame('passed', $screeningJson['screening_status']);
        self::assertSame('pending', $editorialJson['status']);
        self::assertSame('used_in_scenario', $selectionJson['status']);
        self::assertSame('fixture', $metadata['note']);
        self::assertSame($episode->id, $freshTopic->episode?->id);
    }

    public function testRecorderCreatesEpisodeAndRelatedTopicRows(): void
    {
        $characterProfile = CharacterProfile::factory()->create([
            'character_key' => 'dummy_radio',
        ]);
        $recorder = new EpisodeRecorder();

        $episode = $recorder->record(new EpisodeRecordInput(
            result: $this->scenarioResult(),
            pipelineItems: [$this->pipelineItem('upstream:1', 'used_in_scenario')],
            characterProfile: $characterProfile,
            processedAt: CarbonImmutable::parse('2026-05-28T09:00:00+09:00'),
            metadata: ['generator' => 'fixture'],
        ));
        $scenarioJson = $episode->getAttribute('scenario_json');
        $metadata = $episode->getAttribute('metadata');
        $firstTopic = $episode->topics->first();
        self::assertIsArray($scenarioJson);
        self::assertIsArray($metadata);
        self::assertInstanceOf(EpisodeTopic::class, $firstTopic);

        self::assertSame('episode_2026-05-28_0900_dummy_radio', $episode->episode_key);
        self::assertSame(Episode::STATUS_COMPLETED, $episode->status);
        self::assertSame('dummy_radio', $episode->character_key);
        self::assertSame('今日のギークニュース', $scenarioJson['title']);
        self::assertSame('fixture', $metadata['generator']);
        self::assertNull($episode->errors);
        self::assertCount(1, $episode->topics);
        self::assertSame('upstream:1', $firstTopic->topic_id);
        self::assertSame('digestpipe', $firstTopic->upstream_provider);
        self::assertSame('1', $firstTopic->upstream_id);
        self::assertSame('passed', $firstTopic->screening_status);
        self::assertSame('pending', $firstTopic->editorial_status);
        self::assertSame('used_in_scenario', $firstTopic->scenario_selection_status);
    }

    public function testEpisodeKeyIsUnique(): void
    {
        $this->episode(['episode_key' => 'episode_unique']);

        $this->expectException(QueryException::class);

        $this->episode(['episode_key' => 'episode_unique']);
    }

    public function testRecorderDoesNotStoreRawPromptsResponsesBodiesOrSecrets(): void
    {
        $recorder = new EpisodeRecorder();

        $episode = $recorder->record(new EpisodeRecordInput(
            result: $this->scenarioResult(),
            pipelineItems: [$this->pipelineItem('upstream:1', 'used_in_scenario', [
                'prompt' => 'DO NOT STORE PROMPT',
                'raw_model_response' => 'DO NOT STORE RESPONSE',
                'raw_article_body' => 'DO NOT STORE BODY',
                'api_key' => 'DO NOT STORE KEY',
                'nested' => [
                    'authorization' => 'DO NOT STORE AUTH',
                    'safe' => 'keep',
                ],
            ])],
            processedAt: CarbonImmutable::parse('2026-05-28T09:00:00+09:00'),
            metadata: [
                'raw_prompt' => 'DO NOT STORE RAW PROMPT',
                'generator' => 'fixture',
            ],
            errors: [
                [
                    'message' => 'safe summary',
                    'access_token' => 'DO NOT STORE TOKEN',
                ],
            ],
        ));

        $freshEpisode = $episode->fresh(['topics']);
        self::assertInstanceOf(Episode::class, $freshEpisode);
        $stored = json_encode($freshEpisode->toArray(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

        self::assertStringNotContainsString('DO NOT STORE', $stored);
        self::assertStringContainsString('safe summary', $stored);
        self::assertStringContainsString('keep', $stored);
        self::assertSame(Episode::STATUS_COMPLETED_WITH_ERRORS, $episode->status);
    }

    public function testRecorderPreservesScenarioSelectionStatusInEpisodeTopics(): void
    {
        $recorder = new EpisodeRecorder();

        $episode = $recorder->record(new EpisodeRecordInput(
            result: $this->scenarioResult(),
            pipelineItems: [
                $this->pipelineItem('upstream:1', 'used_in_scenario'),
                $this->pipelineItem('upstream:2', 'selected_not_used'),
            ],
            processedAt: CarbonImmutable::parse('2026-05-28T09:00:00+09:00'),
        ));
        $firstTopic = $episode->topics->get(0);
        $secondTopic = $episode->topics->get(1);
        self::assertInstanceOf(EpisodeTopic::class, $firstTopic);
        self::assertInstanceOf(EpisodeTopic::class, $secondTopic);

        self::assertSame('used_in_scenario', $firstTopic->scenario_selection_status);
        self::assertSame('selected_not_used', $secondTopic->scenario_selection_status);
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function episode(array $overrides = []): Episode
    {
        return Episode::query()->create(array_merge([
            'episode_key' => 'episode_' . uniqid(),
            'date' => '2026-05-28',
            'processed_at' => '2026-05-28 09:00:00',
            'status' => Episode::STATUS_COMPLETED,
            'language' => 'ja',
            'scenario_json' => ['title' => 'fixture'],
            'metadata' => [],
        ], $overrides));
    }

    private function scenarioResult(): ScenarioGenerationResult
    {
        return new ScenarioGenerationResult(
            scenario: new Scenario(
                title: '今日のギークニュース',
                language: 'ja',
                targetDurationSeconds: 900,
                estimatedDurationSeconds: 120,
                characterKey: 'dummy_radio',
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
                metadata: ['driver' => 'fixture'],
            ),
            topicSelections: [
                new ScenarioTopicSelection(
                    topicId: 'upstream:1',
                    status: ScenarioTopicSelectionStatus::UsedInScenario,
                    rank: 1,
                    reason: 'fixture',
                ),
            ],
            metadata: ['generator' => 'fixture'],
        );
    }

    /**
     * @param array<string, mixed> $metadata
     *
     * @return array<string, mixed>
     */
    private function pipelineItem(string $topicId, string $selectionStatus, array $metadata = []): array
    {
        $upstreamId = str_replace('upstream:', '', $topicId);

        return [
            'upstream_article' => [
                'upstream_id' => $upstreamId,
                'provider_name' => 'digestpipe',
                'source' => [
                    'name' => 'Laravel News',
                ],
            ],
            'topic_draft' => [
                'id' => $topicId,
                'source_type' => 'upstream',
                'source_name' => 'Laravel News',
                'title' => 'Topic ' . $upstreamId,
                'url' => 'https://example.test/articles/' . $upstreamId,
                'source_refs' => [
                    'provider' => 'digestpipe',
                    'upstream_id' => $upstreamId,
                ],
            ],
            'screening' => [
                'screening_status' => 'passed',
            ],
            'editorial' => [
                'status' => 'pending',
            ],
            'selection' => [
                'topic_id' => $topicId,
                'status' => $selectionStatus,
            ],
            'metadata' => $metadata,
        ];
    }
}
