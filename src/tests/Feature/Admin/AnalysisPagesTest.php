<?php

namespace Tests\Feature\Admin;

use App\Filament\Pages\CandidateTopicsAnalysis;
use App\Filament\Pages\EpisodesAnalysis;
use App\Filament\Widgets\PipelineStatsOverviewWidget;
use App\Models\CandidateTopic;
use App\Models\CharacterProfile;
use App\Models\Episode;
use App\Models\EpisodeTopic;
use App\Models\User;
use Filament\Pages\Dashboard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * @internal
 */
class AnalysisPagesTest extends TestCase
{
    use RefreshDatabase;

    public function testAuthorizedAdminCanAccessDashboardWithPipelineStats(): void
    {
        $this->actingAsAdmin();
        $this->seedPipelineData();

        $this->get(Dashboard::getUrl(panel: 'admin'))
            ->assertOk();

        $component = Livewire::test(PipelineStatsOverviewWidget::class);
        $component->assertSee('Pipeline health');
        $component->assertSee('Episodes / last 24h');
        $component->assertSee('Candidate Topics / last 24h');
    }

    public function testAuthorizedAdminCanAccessEpisodesAnalysis(): void
    {
        $this->actingAsAdmin();
        $this->seedPipelineData();

        $this->get(EpisodesAnalysis::getUrl(panel: 'admin'))
            ->assertOk()
            ->assertSee('Episodes Analysis')
            ->assertSee('Total episodes')
            ->assertSee('Recent Episodes')
            ->assertSee('episode_analysis_test');
    }

    public function testAuthorizedAdminCanAccessCandidateTopicsAnalysis(): void
    {
        $this->actingAsAdmin();
        $this->seedPipelineData();

        $this->get(CandidateTopicsAnalysis::getUrl(panel: 'admin'))
            ->assertOk()
            ->assertSee('Candidate Topics Analysis')
            ->assertSee('Total candidate topics')
            ->assertSee('Recent Candidate Topics')
            ->assertSee('analysis candidate');
    }

    public function testNonAdminUserCannotAccessAnalysisPages(): void
    {
        config(['radiopipe.admin.allowed_emails' => ['admin@example.test']]);

        $this->actingAs(User::factory()->create(['email' => 'user@example.test']));

        $this->get(EpisodesAnalysis::getUrl(panel: 'admin'))->assertForbidden();
        $this->get(CandidateTopicsAnalysis::getUrl(panel: 'admin'))->assertForbidden();
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
     * 分析ページ表示用の dummy pipeline data を作成する。
     */
    private function seedPipelineData(): void
    {
        $profile = CharacterProfile::factory()->create([
            'character_key' => 'analysis_character',
        ]);

        $episode = Episode::query()->create([
            'episode_key' => 'episode_analysis_test',
            'date' => '2026-05-28',
            'published_at' => now(),
            'processed_at' => now(),
            'character_profile_id' => $profile->id,
            'character_key' => $profile->character_key,
            'status' => Episode::STATUS_COMPLETED,
            'title' => '分析テスト番組',
            'language' => 'ja',
            'target_duration_seconds' => 900,
            'estimated_duration_seconds' => 120,
            'scenario_json' => ['title' => '分析テスト番組', 'sections' => []],
            'metadata' => ['generator' => 'fake'],
        ]);

        EpisodeTopic::query()->create([
            'episode_id' => $episode->id,
            'topic_id' => 'upstream:analysis',
            'source_name' => 'Dummy Source',
            'title' => '分析テスト Topic',
            'screening_status' => 'passed',
            'editorial_status' => 'pending',
            'scenario_selection_status' => 'used_in_scenario',
            'sort_order' => 1,
        ]);

        CandidateTopic::query()->create([
            'topic_id' => 'upstream:analysis',
            'source_type' => 'upstream',
            'source_name' => 'Dummy Source',
            'upstream_provider' => 'digestpipe',
            'upstream_id' => 'analysis',
            'article_url' => 'https://example.test/articles/analysis',
            'article_published_at' => now(),
            'topic_draft_json' => ['title' => 'analysis candidate'],
            'screening_json' => ['status' => 'passed'],
            'editorial_json' => ['status' => 'pending'],
            'screening_status' => 'passed',
            'screening_score' => 80,
            'editorial_status' => 'pending',
            'editorial_score' => 70,
            'candidate_fingerprint' => str_repeat('b', 64),
            'processed_at' => now(),
            'metadata' => ['source' => 'dummy'],
        ]);
    }
}
