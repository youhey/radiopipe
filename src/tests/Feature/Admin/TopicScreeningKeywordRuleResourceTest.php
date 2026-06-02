<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\TopicScreeningKeywordRuleResource;
use App\Filament\Resources\TopicScreeningKeywordRuleResource\Pages\ListTopicScreeningKeywordRules;
use App\Models\TopicScreeningKeywordRule;
use App\Models\User;
use App\Topics\Screening\TopicScreeningKeywordRuleSpreadsheet;
use Database\Seeders\TopicScreeningKeywordRuleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Livewire\Livewire;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
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

    public function testFilamentResourceCanDownloadExcelExport(): void
    {
        $this->actingAsAdmin();
        TopicScreeningKeywordRule::factory()->create(['keyword' => 'export keyword']);

        $component = Livewire::test(ListTopicScreeningKeywordRules::class);
        // @phpstan-ignore-next-line Filament の Livewire test macro を使用する。
        $component->assertActionExists('exportExcel');
        // @phpstan-ignore-next-line Filament の Livewire test macro を使用する。
        $component->callAction('exportExcel');
        $component->assertFileDownloaded('topic-screening-keyword-rules.xlsx');
    }

    public function testSpreadsheetImportCreatesAndUpdatesRules(): void
    {
        $existingRule = TopicScreeningKeywordRule::factory()->create([
            'rule_type' => TopicScreeningKeywordRule::TYPE_LIMITATION,
            'keyword' => 'existing keyword',
            'match_type' => TopicScreeningKeywordRule::MATCH_CONTAINS,
            'target_fields' => [TopicScreeningKeywordRule::FIELD_LIMITATIONS],
            'penalty' => 30,
            'severity' => 'medium',
            'action' => TopicScreeningKeywordRule::ACTION_FLAG,
            'is_active' => true,
            'sort_order' => 10,
            'notes' => null,
        ]);
        $path = $this->writeSpreadsheet([
            [
                TopicScreeningKeywordRule::TYPE_LIMITATION,
                'existing keyword',
                TopicScreeningKeywordRule::MATCH_CONTAINS,
                TopicScreeningKeywordRule::FIELD_TITLE . ',' . TopicScreeningKeywordRule::FIELD_LIMITATIONS,
                '12',
                'low',
                TopicScreeningKeywordRule::ACTION_FLAG,
                'false',
                '20',
                'updated note',
            ],
            [
                TopicScreeningKeywordRule::TYPE_SENSITIVE,
                'new keyword',
                TopicScreeningKeywordRule::MATCH_CONTAINS,
                TopicScreeningKeywordRule::FIELD_TITLE,
                '',
                'high',
                TopicScreeningKeywordRule::ACTION_REJECT,
                'true',
                '30',
                'new note',
            ],
        ]);

        $result = app(TopicScreeningKeywordRuleSpreadsheet::class)->import($path);

        self::assertSame(1, $result->createdCount());
        self::assertSame(1, $result->updatedCount());
        self::assertSame(0, $result->skippedCount());

        $existingRule->refresh();
        self::assertSame([
            TopicScreeningKeywordRule::FIELD_TITLE,
            TopicScreeningKeywordRule::FIELD_LIMITATIONS,
        ], $existingRule->target_fields);
        self::assertSame(12, $existingRule->penalty);
        self::assertSame('low', $existingRule->severity);
        self::assertFalse($existingRule->is_active);
        self::assertSame(20, $existingRule->sort_order);
        self::assertSame('updated note', $existingRule->notes);

        $newRule = TopicScreeningKeywordRule::query()
            ->where('keyword', 'new keyword')
            ->firstOrFail();
        self::assertSame(TopicScreeningKeywordRule::TYPE_SENSITIVE, $newRule->rule_type);
        self::assertSame([TopicScreeningKeywordRule::FIELD_TITLE], $newRule->target_fields);
        self::assertSame(TopicScreeningKeywordRule::ACTION_REJECT, $newRule->action);
    }

    public function testSpreadsheetImportRejectsUnsupportedRuleType(): void
    {
        $path = $this->writeSpreadsheet([
            [
                'unsupported',
                'bad keyword',
                TopicScreeningKeywordRule::MATCH_CONTAINS,
                TopicScreeningKeywordRule::FIELD_TITLE,
                '',
                '',
                TopicScreeningKeywordRule::ACTION_FLAG,
                'true',
                '0',
                '',
            ],
        ]);

        $this->expectException(InvalidArgumentException::class);

        app(TopicScreeningKeywordRuleSpreadsheet::class)->import($path);
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
     * test 用 xlsx を作成する。
     *
     * @param list<list<string>> $rows
     */
    private function writeSpreadsheet(array $rows): string
    {
        $path = tempnam(sys_get_temp_dir(), 'radiopipe-screening-rules-test-');

        self::assertIsString($path);

        $writer = new Writer();
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues([
            'rule_type',
            'keyword',
            'match_type',
            'target_fields',
            'penalty',
            'severity',
            'action',
            'is_active',
            'sort_order',
            'notes',
        ]));

        foreach ($rows as $row) {
            $writer->addRow(Row::fromValues($row));
        }

        $writer->close();

        return $path;
    }
}
