<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\CandidateTopicResource;
use App\Filament\Resources\CandidateTopicResource\Pages\ListCandidateTopics;
use App\Filament\Resources\CandidateTopicResource\Pages\ViewCandidateTopic;
use App\Models\CandidateTopic;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * @internal
 */
class CandidateTopicResourceTest extends TestCase
{
    use RefreshDatabase;

    public function testAuthorizedAdminCanAccessCandidateTopicListAndSeeRecords(): void
    {
        $this->actingAsAdmin();
        $candidate = $this->candidateTopic([
            'topic_id' => 'upstream:resource-list',
            'topic_draft_json' => ['title' => '一覧表示 Candidate Topic'],
        ]);

        $this->get(CandidateTopicResource::getUrl('index'))->assertOk();

        $component = Livewire::test(ListCandidateTopics::class);
        // @phpstan-ignore-next-line Filament の Livewire test macro を使用する。
        $component->assertCanSeeTableRecords([$candidate]);
        $component->assertSee('upstream:resource-list');
        $component->assertSee('一覧表示 Candidate Topic');
    }

    public function testAuthorizedAdminCanAccessCandidateTopicViewAndRenderJsonFields(): void
    {
        $this->actingAsAdmin();
        $candidate = $this->candidateTopic([
            'topic_id' => 'upstream:resource-view',
            'topic_draft_json' => ['title' => '詳細表示 Candidate Topic'],
            'screening_json' => ['status' => 'passed'],
            'editorial_json' => ['status' => 'pending'],
            'metadata' => ['source' => 'dummy'],
        ]);

        $this->get(CandidateTopicResource::getUrl('view', ['record' => $candidate]))->assertOk();

        $component = Livewire::test(ViewCandidateTopic::class, ['record' => $candidate->getKey()]);
        $component->assertSee('詳細表示 Candidate Topic');
        $component->assertSee('passed');
        $component->assertSee('pending');
        $component->assertSee('dummy');
    }

    public function testNonAdminUserCannotAccessCandidateTopicList(): void
    {
        config(['radiopipe.admin.allowed_emails' => ['admin@example.test']]);

        $this->actingAs(User::factory()->create(['email' => 'user@example.test']));

        $this->get(CandidateTopicResource::getUrl('index'))->assertForbidden();
    }

    public function testCandidateTopicResourceIsReadOnly(): void
    {
        $candidate = $this->candidateTopic();

        self::assertFalse(CandidateTopicResource::canCreate());
        self::assertFalse(CandidateTopicResource::canEdit($candidate));
        self::assertFalse(CandidateTopicResource::canDelete($candidate));
        self::assertFalse(CandidateTopicResource::canDeleteAny());
        self::assertFalse(CandidateTopicResource::hasPage('create'));
        self::assertFalse(CandidateTopicResource::hasPage('edit'));
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
     * テスト用 CandidateTopic を作成する。
     *
     * @param array<string, mixed> $overrides
     */
    private function candidateTopic(array $overrides = []): CandidateTopic
    {
        return CandidateTopic::query()->create(array_merge([
            'topic_id' => 'upstream:1',
            'source_type' => 'upstream',
            'source_name' => 'Dummy Source',
            'upstream_provider' => 'digestpipe',
            'upstream_id' => '1',
            'article_url' => 'https://example.test/articles/1',
            'article_published_at' => '2026-05-28 07:00:00',
            'topic_draft_json' => ['title' => 'ダミー Candidate Topic'],
            'screening_json' => ['status' => 'passed'],
            'editorial_json' => ['status' => 'pending'],
            'screening_status' => 'passed',
            'screening_score' => 80,
            'editorial_status' => 'pending',
            'editorial_score' => 70,
            'candidate_fingerprint' => str_repeat('a', 64),
            'processed_at' => '2026-05-28 07:01:00',
            'metadata' => ['source' => 'dummy'],
        ], $overrides));
    }
}
