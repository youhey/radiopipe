<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\EpisodeTopicResource;
use App\Filament\Resources\EpisodeTopicResource\Pages\ListEpisodeTopics;
use App\Filament\Resources\EpisodeTopicResource\Pages\ViewEpisodeTopic;
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
class EpisodeTopicResourceTest extends TestCase
{
    use RefreshDatabase;

    public function testAuthorizedAdminCanAccessEpisodeTopicListAndSeeRecords(): void
    {
        $this->actingAsAdmin();
        $topic = $this->episodeTopic(['topic_id' => 'upstream:123', 'title' => '一覧表示トピック']);

        $this->get(EpisodeTopicResource::getUrl('index'))->assertOk();

        $component = Livewire::test(ListEpisodeTopics::class);
        // @phpstan-ignore-next-line Filament の Livewire test macro を使用する。
        $component->assertCanSeeTableRecords([$topic]);
        $component->assertSee('upstream:123');
        $component->assertSee('一覧表示トピック');
    }

    public function testAuthorizedAdminCanAccessEpisodeTopicViewAndRenderJsonFields(): void
    {
        $this->actingAsAdmin();
        $topic = $this->episodeTopic([
            'topic_draft_json' => ['title' => '下書きタイトル'],
            'screening_json' => ['screening_status' => 'passed'],
            'editorial_json' => ['editorial_status' => 'pending'],
            'scenario_selection_json' => ['status' => 'used_in_scenario'],
            'metadata' => ['source' => 'dummy'],
        ]);

        $this->get(EpisodeTopicResource::getUrl('view', ['record' => $topic]))->assertOk();

        $component = Livewire::test(ViewEpisodeTopic::class, ['record' => $topic->getKey()]);
        $component->assertSee('下書きタイトル');
        $component->assertSee('passed');
        $component->assertSee('pending');
        $component->assertSee('used_in_scenario');
        $component->assertSee('dummy');
    }

    public function testAuthorizedAdminCanExportEpisodeTopicJsonFromView(): void
    {
        $this->actingAsAdmin();
        $topic = $this->episodeTopic([
            'topic_id' => 'upstream:export-topic',
            'title' => 'エクスポート対象トピック',
            'topic_draft_json' => ['title' => '下書きタイトル'],
            'metadata' => ['source' => 'dummy'],
        ]);

        $component = Livewire::test(ViewEpisodeTopic::class, ['record' => $topic->getKey()]);
        // @phpstan-ignore-next-line Filament の Livewire test macro を使用する。
        $component->assertActionExists('export');
        // @phpstan-ignore-next-line Filament の Livewire test macro を使用する。
        $component->callAction('export');
        $component->assertFileDownloaded('episode-topic-upstream-export-topic.json');

        $payload = EpisodeTopicResource::exportPayload($topic);

        self::assertSame('episode_topic', $payload['type']);
        self::assertSame('upstream:export-topic', data_get($payload, 'episode_topic.topic_id'));
        self::assertSame('下書きタイトル', data_get($payload, 'episode_topic.topic_draft_json.title'));
        self::assertSame('dummy', data_get($payload, 'episode_topic.metadata.source'));
    }

    public function testAuthorizedAdminCanExportEpisodeTopicJsonFromListTable(): void
    {
        $this->actingAsAdmin();
        $topic = $this->episodeTopic([
            'topic_id' => 'upstream:list-export-topic',
            'title' => '一覧エクスポート対象トピック',
        ]);

        $component = Livewire::test(ListEpisodeTopics::class);
        // @phpstan-ignore-next-line Filament の Livewire test macro を使用する。
        $component->assertTableActionExists('export', record: $topic);
        // @phpstan-ignore-next-line Filament の Livewire test macro を使用する。
        $component->callTableAction('export', $topic);
        $component->assertFileDownloaded('episode-topic-upstream-list-export-topic.json');
    }

    public function testNonAdminUserCannotAccessEpisodeTopicList(): void
    {
        config(['radiopipe.admin.allowed_emails' => ['admin@example.test']]);

        $this->actingAs(User::factory()->create(['email' => 'user@example.test']));

        $this->get(EpisodeTopicResource::getUrl('index'))->assertForbidden();
    }

    public function testEpisodeTopicResourceIsReadOnly(): void
    {
        $topic = $this->episodeTopic();

        self::assertFalse(EpisodeTopicResource::canCreate());
        self::assertFalse(EpisodeTopicResource::canEdit($topic));
        self::assertFalse(EpisodeTopicResource::canDelete($topic));
        self::assertFalse(EpisodeTopicResource::canDeleteAny());
        self::assertFalse(EpisodeTopicResource::hasPage('create'));
        self::assertFalse(EpisodeTopicResource::hasPage('edit'));
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
     * テスト用 EpisodeTopic を作成する。
     *
     * @param array<string, mixed> $overrides
     */
    private function episodeTopic(array $overrides = []): EpisodeTopic
    {
        $profile = CharacterProfile::factory()->create([
            'character_key' => 'dummy_character',
        ]);
        $episode = Episode::query()->create([
            'episode_key' => 'episode_2026-05-28_0700_dummy_character',
            'date' => '2026-05-28',
            'published_at' => '2026-05-28 07:00:00',
            'processed_at' => '2026-05-28 07:01:00',
            'character_profile_id' => $profile->id,
            'character_key' => $profile->character_key,
            'status' => Episode::STATUS_COMPLETED,
            'title' => 'ダミー番組',
            'language' => 'ja',
            'scenario_json' => [
                'title' => 'ダミー番組',
                'script_text' => 'テスト用の読み上げ原稿です。',
            ],
            'metadata' => ['generator' => 'fake'],
        ]);

        return EpisodeTopic::query()->create(array_merge([
            'episode_id' => $episode->id,
            'topic_id' => 'upstream:1',
            'upstream_provider' => 'digestpipe',
            'upstream_id' => '1',
            'source_name' => 'Dummy Source',
            'source_type' => 'upstream',
            'title' => 'ダミートピック',
            'url' => 'https://example.test/articles/1',
            'screening_status' => 'passed',
            'editorial_status' => 'pending',
            'scenario_selection_status' => 'used_in_scenario',
            'sort_order' => 1,
            'topic_draft_json' => ['title' => 'ダミートピック'],
            'screening_json' => ['screening_status' => 'passed'],
            'editorial_json' => ['editorial_status' => 'pending'],
            'scenario_selection_json' => ['status' => 'used_in_scenario'],
            'metadata' => ['source' => 'dummy'],
        ], $overrides));
    }
}
