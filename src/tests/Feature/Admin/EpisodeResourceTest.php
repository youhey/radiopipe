<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\EpisodeResource;
use App\Filament\Resources\EpisodeResource\Pages\ListEpisodes;
use App\Filament\Resources\EpisodeResource\Pages\ViewEpisode;
use App\Models\CharacterProfile;
use App\Models\Episode;
use App\Models\EpisodeTopic;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * @internal
 */
class EpisodeResourceTest extends TestCase
{
    use RefreshDatabase;

    public function testAuthorizedAdminCanAccessEpisodeListAndSeeRecords(): void
    {
        $this->actingAsAdmin();
        $episode = $this->episode(['episode_key' => 'episode_2026-05-28_0700_dummy']);

        $this->get(EpisodeResource::getUrl('index'))->assertOk();

        $component = Livewire::test(ListEpisodes::class);
        // @phpstan-ignore-next-line Filament の Livewire test macro を使用する。
        $component->assertCanSeeTableRecords([$episode]);
        $component->assertSee('episode_2026-05-28_0700_dummy');
    }

    public function testAuthorizedAdminCanAccessEpisodeViewAndRenderJsonFields(): void
    {
        $this->actingAsAdmin();
        $episode = $this->episode([
            'scenario_json' => [
                'title' => 'ダミー番組',
                'language' => 'ja',
                'script_text' => 'テスト用の読み上げ原稿です。',
                'sections' => [
                    ['type' => 'opening', 'title' => 'オープニング'],
                ],
            ],
            'metadata' => ['generator' => 'fake'],
            'errors' => [['stage' => 'editorial', 'message' => 'dummy error']],
        ]);
        EpisodeTopic::query()->create([
            'episode_id' => $episode->id,
            'topic_id' => 'upstream:1',
            'title' => 'ダミートピック',
            'screening_status' => 'passed',
            'editorial_status' => 'pending',
            'scenario_selection_status' => 'used_in_scenario',
            'sort_order' => 1,
        ]);

        $this->get(EpisodeResource::getUrl('view', ['record' => $episode]))->assertOk();

        $component = Livewire::test(ViewEpisode::class, ['record' => $episode->getKey()]);
        $component->assertSee('ダミー番組');
        $component->assertSee('テスト用の読み上げ原稿です。');
        $component->assertSee('dummy error');
        $component->assertSee('ダミートピック');
    }

    public function testNonAdminUserCannotAccessEpisodeList(): void
    {
        config(['radiopipe.admin.allowed_emails' => ['admin@example.test']]);

        $this->actingAs(User::factory()->create(['email' => 'user@example.test']));

        $this->get(EpisodeResource::getUrl('index'))->assertForbidden();
    }

    public function testEpisodeResourceIsReadOnly(): void
    {
        $episode = $this->episode();

        self::assertFalse(EpisodeResource::canCreate());
        self::assertFalse(EpisodeResource::canEdit($episode));
        self::assertFalse(EpisodeResource::canDelete($episode));
        self::assertFalse(EpisodeResource::canDeleteAny());
        self::assertFalse(EpisodeResource::hasPage('create'));
        self::assertFalse(EpisodeResource::hasPage('edit'));
    }

    /**
     * 管理者としてログインする。
     */
    private function actingAsAdmin(): void
    {
        config(['radiopipe.admin.allowed_emails' => ['admin@example.test']]);

        $this->actingAs(User::factory()->create(['email' => 'admin@example.test']));
    }

    /**
     * テスト用 Episode を作成する。
     *
     * @param array<string, mixed> $overrides
     */
    private function episode(array $overrides = []): Episode
    {
        $profile = CharacterProfile::factory()->create([
            'character_key' => 'dummy_character',
            'name' => 'ダミーキャラクター',
        ]);

        return Episode::query()->create(array_merge([
            'episode_key' => 'episode_2026-05-28_0700_dummy_character',
            'date' => '2026-05-28',
            'published_at' => '2026-05-28 07:00:00',
            'processed_at' => '2026-05-28 07:01:00',
            'character_profile_id' => $profile->id,
            'character_key' => $profile->character_key,
            'status' => Episode::STATUS_COMPLETED,
            'title' => 'ダミー番組',
            'language' => 'ja',
            'target_duration_seconds' => 900,
            'estimated_duration_seconds' => 120,
            'scenario_json' => [
                'title' => 'ダミー番組',
                'language' => 'ja',
                'script_text' => 'テスト用の読み上げ原稿です。',
                'sections' => [],
            ],
            'metadata' => ['generator' => 'fake'],
            'errors' => null,
        ], $overrides));
    }
}
