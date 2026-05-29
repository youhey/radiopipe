<?php

namespace Tests\Feature\Api;

use App\ApiTokens\ApiTokenService;
use App\Models\Episode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\PendingCommand;
use Tests\TestCase;

/**
 * @internal
 */
class EpisodesApiTest extends TestCase
{
    use RefreshDatabase;

    public function testEpisodesApiIsAuthenticatedByReadToken(): void
    {
        $this->episode();
        $user = User::factory()->create(['email' => 'api-user@example.test']);
        $plainTextToken = $user->createToken('radiopipe-api', [ApiTokenService::ABILITY_EPISODES_READ])->plainTextToken;

        $this->withHeader('Authorization', 'Bearer ' . $plainTextToken)
            ->getJson('/api/episodes')
            ->assertOk()
            ->assertJsonPath('schema_version', '1.0')
            ->assertJsonPath('data.0.episode_key', 'episode_2026-05-29_0700_dummy');
    }

    public function testEpisodesApiRejectsMissingToken(): void
    {
        $this->getJson('/api/episodes')
            ->assertUnauthorized();
    }

    public function testEpisodesApiRejectsTokenWithoutRequiredAbility(): void
    {
        $user = User::factory()->create(['email' => 'api-user@example.test']);
        $plainTextToken = $user->createToken('radiopipe-api', ['other:read'])->plainTextToken;

        $this->withHeader('Authorization', 'Bearer ' . $plainTextToken)
            ->getJson('/api/episodes')
            ->assertForbidden();
    }

    public function testEpisodesApiRejectsRotatedOldToken(): void
    {
        $this->episode();
        $user = User::factory()->create(['email' => 'api-user@example.test']);
        $oldPlainTextToken = $user->createToken('radiopipe-api', [ApiTokenService::ABILITY_EPISODES_READ])->plainTextToken;

        /** @var PendingCommand $command */
        $command = $this->artisan('radiopipe:users:rotate-api-token', [
            'email' => 'api-user@example.test',
            '--token-name' => 'radiopipe-api',
            '--ability' => [ApiTokenService::ABILITY_EPISODES_READ],
        ]);

        $command->assertSuccessful()
            ->execute();

        $this->withHeader('Authorization', 'Bearer ' . $oldPlainTextToken)
            ->getJson('/api/episodes')
            ->assertUnauthorized();
    }

    /**
     * テスト用 Episode を作成する。
     */
    private function episode(): Episode
    {
        return Episode::query()->create([
            'episode_key' => 'episode_2026-05-29_0700_dummy',
            'date' => '2026-05-29',
            'published_at' => '2026-05-29 07:00:00',
            'processed_at' => '2026-05-29 07:01:00',
            'character_key' => 'dummy_character',
            'status' => Episode::STATUS_COMPLETED,
            'title' => 'API テスト番組',
            'language' => 'ja',
            'target_duration_seconds' => 900,
            'estimated_duration_seconds' => 120,
            'scenario_json' => [
                'title' => 'API テスト番組',
                'script_text' => 'テスト用の読み上げ原稿です。',
                'sections' => [],
            ],
            'metadata' => ['generator' => 'fake'],
        ]);
    }
}
