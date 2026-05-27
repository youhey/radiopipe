<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\TopicScreeningKeywordRuleResource;
use App\Filament\Resources\TopicScreeningKeywordRuleResource\Pages\ListTopicScreeningKeywordRules;
use App\Models\TopicScreeningKeywordRule;
use App\Models\User;
use Database\Seeders\TopicScreeningKeywordRuleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * @internal
 */
class TopicScreeningKeywordRuleResourceTest extends TestCase
{
    use RefreshDatabase;

    public function testSeederCreatesLimitationAndSensitiveRules(): void
    {
        $this->seed(TopicScreeningKeywordRuleSeeder::class);

        self::assertDatabaseHas('topic_screening_keyword_rules', [
            'rule_type' => TopicScreeningKeywordRule::TYPE_LIMITATION,
            'keyword' => 'headline only',
            'match_type' => TopicScreeningKeywordRule::MATCH_CONTAINS,
            'penalty' => 30,
            'action' => TopicScreeningKeywordRule::ACTION_FLAG,
            'is_active' => true,
        ]);
        self::assertDatabaseHas('topic_screening_keyword_rules', [
            'rule_type' => TopicScreeningKeywordRule::TYPE_SENSITIVE,
            'keyword' => 'security breach',
            'match_type' => TopicScreeningKeywordRule::MATCH_CONTAINS,
            'penalty' => null,
            'action' => TopicScreeningKeywordRule::ACTION_REJECT,
            'is_active' => true,
        ]);
    }

    public function testSeederIsIdempotent(): void
    {
        $this->seed(TopicScreeningKeywordRuleSeeder::class);
        $this->seed(TopicScreeningKeywordRuleSeeder::class);

        self::assertSame(37, DB::table('topic_screening_keyword_rules')->count());
    }

    public function testModelCastsTargetFieldsAndScalarFields(): void
    {
        $rule = TopicScreeningKeywordRule::factory()->create([
            'target_fields' => [
                TopicScreeningKeywordRule::FIELD_TITLE,
                TopicScreeningKeywordRule::FIELD_LIMITATIONS,
            ],
            'penalty' => 12,
            'is_active' => false,
            'sort_order' => 7,
        ])->refresh();

        self::assertSame([
            TopicScreeningKeywordRule::FIELD_TITLE,
            TopicScreeningKeywordRule::FIELD_LIMITATIONS,
        ], $rule->target_fields);
        self::assertSame(12, $rule->penalty);
        self::assertFalse($rule->is_active);
        self::assertSame(7, $rule->sort_order);
    }

    public function testFilamentResourceCanRenderForAuthorizedAdminUser(): void
    {
        $this->actingAsAdmin();
        $rule = TopicScreeningKeywordRule::factory()->create(['keyword' => 'dummy keyword']);

        $this->get(TopicScreeningKeywordRuleResource::getUrl('index'))->assertOk();

        $component = Livewire::test(ListTopicScreeningKeywordRules::class);
        // @phpstan-ignore-next-line Filament の Livewire test macro を使用する。
        $component->assertCanSeeTableRecords([$rule]);
    }

    /**
     * 管理者としてログインする。
     */
    private function actingAsAdmin(): void
    {
        config(['radiopipe.admin.allowed_emails' => ['admin@example.test']]);

        $this->actingAs(User::factory()->create(['email' => 'admin@example.test']));
    }
}
