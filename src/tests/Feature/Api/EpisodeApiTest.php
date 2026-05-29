<?php

namespace Tests\Feature\Api;

use App\ApiTokens\ApiTokenService;
use App\Models\CharacterProfile;
use App\Models\Episode;
use App\Models\EpisodeTopic;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * @internal
 */
class EpisodeApiTest extends TestCase
{
    use RefreshDatabase;

    public function testUnauthenticatedRequestIsRejected(): void
    {
        $this->getJson('/api/episodes')
            ->assertUnauthorized();
    }

    public function testTokenWithoutEpisodesReadAbilityIsRejected(): void
    {
        $plainTextToken = User::factory()
            ->create(['email' => 'api-user@example.test'])
            ->createToken('radiopipe-api', ['other:read'])
            ->plainTextToken;

        $this->withReadToken($plainTextToken)
            ->getJson('/api/episodes')
            ->assertForbidden();
    }

    public function testReadTokenCanAccessLightweightIndex(): void
    {
        $this->episode(['episode_key' => 'episode_completed']);
        $this->episode([
            'episode_key' => 'episode_failed',
            'status' => Episode::STATUS_FAILED,
            'published_at' => '2026-05-29 08:00:00',
        ]);

        $this->withReadToken()
            ->getJson('/api/episodes')
            ->assertOk()
            ->assertJsonPath('data.0.episode_key', 'episode_completed')
            ->assertJsonPath('data.0.character.key', 'dummy_character')
            ->assertJsonPath('data.0.character.name', 'ダミーキャラクター')
            ->assertJsonPath('meta.count', 1)
            ->assertJsonPath('meta.limit', 100)
            ->assertJsonMissingPath('data.0.scenario')
            ->assertJsonMissingPath('data.0.scenario_json')
            ->assertJsonMissingPath('data.0.topics')
            ->assertJsonMissingPath('data.0.metadata');
    }

    public function testIndexSupportsLimitStatusCharacterAndDateFilters(): void
    {
        $this->episode([
            'episode_key' => 'episode_a',
            'character_key' => 'dummy_character',
            'published_at' => '2026-05-28 07:00:00',
        ]);
        $this->episode([
            'episode_key' => 'episode_b',
            'character_key' => 'other_character',
            'published_at' => '2026-05-29 07:00:00',
        ]);
        $this->episode([
            'episode_key' => 'episode_c',
            'character_key' => 'dummy_character',
            'published_at' => '2026-05-30 07:00:00',
        ]);

        $this->withReadToken()
            ->getJson('/api/episodes?limit=1&character=dummy_character&from=2026-05-29&to=2026-05-30T23:59:59')
            ->assertOk()
            ->assertJsonPath('data.0.episode_key', 'episode_c')
            ->assertJsonPath('meta.count', 1)
            ->assertJsonPath('meta.limit', 1);
    }

    public function testIndexLimitIsClampedToFiveHundred(): void
    {
        $this->episode();

        $this->withReadToken()
            ->getJson('/api/episodes?limit=1000')
            ->assertOk()
            ->assertJsonPath('meta.limit', 500);
    }

    public function testLatestReturnsLatestCompletedEpisodeDetail(): void
    {
        $older = $this->episode([
            'episode_key' => 'episode_older',
            'published_at' => '2026-05-28 07:00:00',
        ]);
        $this->topic($older, ['topic_id' => 'upstream:older']);

        $latest = $this->episode([
            'episode_key' => 'episode_latest',
            'published_at' => '2026-05-29 07:00:00',
        ]);
        $this->topic($latest);

        $this->episode([
            'episode_key' => 'episode_failed_newer',
            'status' => Episode::STATUS_FAILED,
            'published_at' => '2026-05-30 07:00:00',
        ]);

        $this->withReadToken()
            ->getJson('/api/episodes/latest')
            ->assertOk()
            ->assertJsonPath('data.episode_key', 'episode_latest')
            ->assertJsonPath('data.scenario.title', 'API テスト番組')
            ->assertJsonPath('data.topics.0.topic_id', 'upstream:236')
            ->assertJsonPath('data.topics.0.status', 'used_in_scenario')
            ->assertJsonPath('data.topics.0.title', 'Episode topic title')
            ->assertJsonPath('data.topics.0.summary', 'Localized summary')
            ->assertJsonPath('data.topics.0.why_it_matters', 'Localized why it matters')
            ->assertJsonPath('data.topics.0.discussion_url', 'https://news.ycombinator.com/item?id=236')
            ->assertJsonMissingPath('data.topics.0.topic_draft_json')
            ->assertJsonMissingPath('data.topics.0.screening_json')
            ->assertJsonMissingPath('data.topics.0.editorial_json')
            ->assertJsonMissingPath('data.topics.0.scenario_selection_json')
            ->assertJsonMissingPath('data.errors')
            ->assertJsonMissingPath('data.metadata');
    }

    public function testLatestReturnsNotFoundWhenNoCompletedEpisodeExists(): void
    {
        $this->episode([
            'episode_key' => 'episode_failed',
            'status' => Episode::STATUS_FAILED,
        ]);

        $this->withReadToken()
            ->getJson('/api/episodes/latest')
            ->assertNotFound();
    }

    public function testShowReturnsCompletedEpisodeDetailByKey(): void
    {
        $episode = $this->episode(['episode_key' => 'episode_show']);
        $this->topic($episode, [
            'scenario_selection_status' => 'selected_not_used',
            'sort_order' => 2,
        ]);

        $this->withReadToken()
            ->getJson('/api/episodes/episode_show')
            ->assertOk()
            ->assertJsonPath('data.episode_key', 'episode_show')
            ->assertJsonPath('data.topics.0.status', 'selected_not_used')
            ->assertJsonPath('data.topics.0.sort_order', 2);
    }

    public function testShowReturnsNotFoundForFailedEpisode(): void
    {
        $this->episode([
            'episode_key' => 'episode_failed',
            'status' => Episode::STATUS_FAILED,
        ]);

        $this->withReadToken()
            ->getJson('/api/episodes/episode_failed')
            ->assertNotFound();
    }

    public function testShowReturnsNotFoundForMissingEpisodeKey(): void
    {
        $this->withReadToken()
            ->getJson('/api/episodes/missing_episode')
            ->assertNotFound();
    }

    public function testLatestRouteIsDefinedBeforeEpisodeKeyRoute(): void
    {
        $this->episode(['episode_key' => 'episode_not_latest']);

        $this->withReadToken()
            ->getJson('/api/episodes/latest')
            ->assertOk()
            ->assertJsonPath('data.episode_key', 'episode_not_latest');
    }

    /**
     * episodes:read token を付けた test client を返す。
     */
    private function withReadToken(?string $plainTextToken = null): self
    {
        if ($plainTextToken === null) {
            $plainTextToken = User::factory()
                ->create(['email' => 'api-user@example.test'])
                ->createToken('radiopipe-api', [ApiTokenService::ABILITY_EPISODES_READ])
                ->plainTextToken;
        }

        return $this->withHeader('Authorization', 'Bearer ' . $plainTextToken);
    }

    /**
     * テスト用 Episode を作成する。
     *
     * @param array<string, mixed> $overrides
     */
    private function episode(array $overrides = []): Episode
    {
        $characterKeyValue = $overrides['character_key'] ?? 'dummy_character';
        $characterKey = is_string($characterKeyValue) ? $characterKeyValue : 'dummy_character';
        $profile = CharacterProfile::query()
            ->where('character_key', $characterKey)
            ->first();

        if (! $profile instanceof CharacterProfile) {
            $profile = CharacterProfile::factory()->create([
                'character_key' => $characterKey,
                'name' => $characterKey === 'dummy_character' ? 'ダミーキャラクター' : '別キャラクター',
            ]);
        }

        return Episode::query()->create(array_merge([
            'episode_key' => 'episode_2026-05-29_0700_dummy',
            'date' => '2026-05-29',
            'published_at' => '2026-05-29 07:00:00',
            'processed_at' => '2026-05-29 07:01:00',
            'character_profile_id' => $profile->id,
            'character_key' => $profile->character_key,
            'status' => Episode::STATUS_COMPLETED,
            'title' => 'API テスト番組',
            'language' => 'ja',
            'target_duration_seconds' => 900,
            'estimated_duration_seconds' => 120,
            'scenario_json' => [
                'title' => 'API テスト番組',
                'language' => 'ja',
                'target_duration_seconds' => 900,
                'estimated_duration_seconds' => 120,
                'character_key' => $profile->character_key,
                'script_text' => 'テスト用の読み上げ原稿です。',
                'sections' => [],
                'metadata' => [],
            ],
            'metadata' => ['generator' => 'fake'],
            'errors' => null,
        ], $overrides));
    }

    /**
     * テスト用 EpisodeTopic を作成する。
     *
     * @param array<string, mixed> $overrides
     */
    private function topic(Episode $episode, array $overrides = []): EpisodeTopic
    {
        return EpisodeTopic::query()->create(array_merge([
            'episode_id' => $episode->id,
            'topic_id' => 'upstream:236',
            'upstream_provider' => 'fake',
            'upstream_id' => '236',
            'source_name' => 'Laravel News',
            'source_type' => 'rss',
            'title' => 'Episode topic title',
            'url' => 'https://example.test/article',
            'screening_status' => 'passed',
            'editorial_status' => 'pending',
            'scenario_selection_status' => 'used_in_scenario',
            'sort_order' => 1,
            'topic_draft_json' => [
                'title' => 'Draft title',
                'summary_seed' => 'Draft summary',
                'why_it_matters_seed' => 'Draft why it matters',
                'source_name' => 'Draft Source',
                'url' => 'https://example.test/draft',
                'discussion_url' => 'https://news.ycombinator.com/item?id=236',
            ],
            'screening_json' => ['status' => 'passed'],
            'editorial_json' => [
                'localized' => [
                    'title' => 'Localized title',
                    'summary' => 'Localized summary',
                    'why_it_matters' => 'Localized why it matters',
                ],
            ],
            'scenario_selection_json' => ['status' => 'used_in_scenario'],
            'metadata' => ['internal' => 'hidden'],
        ], $overrides));
    }
}
