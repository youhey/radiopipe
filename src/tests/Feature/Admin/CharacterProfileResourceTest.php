<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\CharacterProfileResource;
use App\Filament\Resources\CharacterProfileResource\Pages\CreateCharacterProfile;
use App\Filament\Resources\CharacterProfileResource\Pages\EditCharacterProfile;
use App\Filament\Resources\CharacterProfileResource\Pages\ListCharacterProfiles;
use App\Models\CharacterProfile;
use App\Models\User;
use Database\Seeders\CharacterProfileSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * @internal
 */
class CharacterProfileResourceTest extends TestCase
{
    use RefreshDatabase;

    public function testModelCastsJsonFieldsToArrays(): void
    {
        $profile = CharacterProfile::factory()->create([
            'catchphrases' => ['さてさて', '確認します'],
            'speech_style' => ['language' => 'ja', 'uses_listener_address' => true],
            'is_active' => false,
            'sort_order' => 5,
        ])->refresh();

        $catchphrases = $profile->getAttribute('catchphrases');
        $speechStyle = $profile->getAttribute('speech_style');

        self::assertIsArray($catchphrases);
        self::assertSame(['さてさて', '確認します'], $catchphrases);
        self::assertIsArray($speechStyle);
        self::assertFalse($profile->is_active);
        self::assertSame(5, $profile->sort_order);
    }

    public function testSeederCreatesOrUpdatesNekoNyanSampleProfile(): void
    {
        $this->seed(CharacterProfileSeeder::class);
        $this->seed(CharacterProfileSeeder::class);

        $profile = CharacterProfile::query()
            ->where('character_key', 'neko_nyan_balanced_radio')
            ->sole();

        self::assertSame('ねこにゃん', $profile->name);
        self::assertSame(10, $profile->sort_order);
        $metadata = $profile->getAttribute('metadata');

        self::assertIsArray($metadata);
        self::assertSame('radiopipe', $metadata['created_for']);
    }

    public function testFilamentResourceCanRenderForAuthorizedAdminUser(): void
    {
        $this->actingAsAdmin();
        $profile = CharacterProfile::factory()->create(['name' => 'ダミー表示キャラクター']);

        $this->get(CharacterProfileResource::getUrl('index'))->assertOk();

        $component = Livewire::test(ListCharacterProfiles::class);
        // @phpstan-ignore-next-line Filament の Livewire test macro を使用する。
        $component->assertCanSeeTableRecords([$profile]);
    }

    public function testCreateFormStoresNewlineTextareaFieldsAsArrays(): void
    {
        $this->actingAsAdmin();

        $component = Livewire::test(CreateCharacterProfile::class);
        // @phpstan-ignore-next-line Filament の Livewire test macro を使用する。
        $component->fillForm($this->validFormData([
            'catchphrases' => "  さてさて  \n\n確認するにゃ\n",
            'style_examples' => "例文1\n例文2",
            'is_active' => false,
        ]));
        $component->call('create');
        // @phpstan-ignore-next-line Filament の Livewire test macro を使用する。
        $component->assertHasNoFormErrors();

        $profile = CharacterProfile::query()
            ->where('character_key', 'dummy_radio_test')
            ->sole();

        self::assertSame(['さてさて', '確認するにゃ'], $profile->getAttribute('catchphrases'));
        self::assertSame(['例文1', '例文2'], $profile->getAttribute('style_examples'));
        self::assertFalse($profile->is_active);
        $metadata = $profile->getAttribute('metadata');
        self::assertIsArray($metadata);
        self::assertSame('1.0', $metadata['schema_version']);
        self::assertSame('radiopipe', $metadata['created_for']);
    }

    public function testEditFormDisplaysArrayFieldsAsNewlineText(): void
    {
        $this->actingAsAdmin();
        $profile = CharacterProfile::factory()->create([
            'catchphrases' => ['さてさて', '確認するにゃ'],
        ]);

        $component = Livewire::test(EditCharacterProfile::class, ['record' => $profile->getKey()]);
        // @phpstan-ignore-next-line Filament の Livewire test macro を使用する。
        $component->assertFormSet([
            'catchphrases' => implode(PHP_EOL, ['さてさて', '確認するにゃ']),
        ]);
    }

    public function testCharacterKeyIsUniqueInFilamentValidation(): void
    {
        $this->actingAsAdmin();
        CharacterProfile::factory()->create(['character_key' => 'duplicate_key']);

        $component = Livewire::test(CreateCharacterProfile::class);
        // @phpstan-ignore-next-line Filament の Livewire test macro を使用する。
        $component->fillForm($this->validFormData(['character_key' => 'duplicate_key']));
        $component->call('create');
        // @phpstan-ignore-next-line Filament の Livewire test macro を使用する。
        $component->assertHasFormErrors(['character_key' => 'unique']);
    }

    public function testLineCountValidationRejectsTooManyLines(): void
    {
        $this->actingAsAdmin();

        $component = Livewire::test(CreateCharacterProfile::class);
        // @phpstan-ignore-next-line Filament の Livewire test macro を使用する。
        $component->fillForm($this->validFormData([
            'catchphrases' => implode(PHP_EOL, array_fill(0, 31, '多すぎる行')),
        ]));
        $component->call('create');
        // @phpstan-ignore-next-line Filament の Livewire test macro を使用する。
        $component->assertHasFormErrors(['catchphrases']);
    }

    public function testToScenarioProfileArrayIncludesFixedMetadataFields(): void
    {
        $profile = CharacterProfile::factory()->create([
            'metadata' => [
                'direction' => 'dummy_direction',
                'reference_policy' => 'dummy_reference',
            ],
        ]);

        $scenarioProfile = $profile->toScenarioProfileArray();
        $metadata = $scenarioProfile['metadata'] ?? null;

        self::assertIsArray($metadata);
        self::assertSame('1.0', $metadata['schema_version']);
        self::assertSame('radiopipe', $metadata['created_for']);
        self::assertSame('Scenario generation instructions', $metadata['intended_use']);
        self::assertSame('dummy_direction', $metadata['direction']);
        self::assertSame('dummy_reference', $metadata['reference_policy']);
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
     * CharacterProfile 作成フォームの有効なテストデータを返す。
     *
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    private function validFormData(array $overrides = []): array
    {
        $data = [
            'character_key' => 'dummy_radio_test',
            'name' => 'ダミーラジオ',
            'role' => 'テスト用の架空ラジオパーソナリティ',
            'personality' => '公開テスト用の架空プロフィールです。',
            'tone' => '中立で読みやすい',
            'speech_style.language' => 'ja',
            'speech_style.sentence_length' => 'short_to_medium',
            'speech_style.uses_listener_address' => true,
            'speech_style.listener_name' => 'キミ',
            'speech_style.first_person' => 'わたし',
            'speech_style.ending_style' => '読みやすい口調',
            'speech_style.pace' => '短い区切りを多めにする',
            'speech_style.rhythm' => '要点を整理する',
            'catchphrases' => 'さてさて',
            'style_examples' => 'さてさて、今日の話題です。',
            'banned_phrases' => '絶対に安全です',
            'disallowed_expressions' => '未確認情報を断定する',
            'serious_topic_behavior.tone' => '抑制的で中立',
            'serious_topic_behavior.allow_jokes' => false,
            'serious_topic_behavior.required_style' => '事実と未確認点を分ける',
            'serious_topic_behavior.catchphrase_limit' => '原則0個',
            'serious_topic_behavior.applies_to' => '災害',
            'content_policy.factuality_priority' => '事実正確性を優先する',
            'content_policy.uncertainty_handling' => '不確実な内容は限定的に扱う',
            'content_policy.source_limitations' => 'limitations を反映する',
            'content_policy.no_fabrication' => true,
            'content_policy.identity_safety' => '架空サンプルです',
            'script_preferences.opening_style' => '短く入る',
            'script_preferences.transition_style' => '短いブリッジを入れる',
            'script_preferences.closing_style' => '要点を振り返る',
            'script_preferences.preferred_segment_roles' => 'main_story',
            'metadata.direction' => 'sample_safe_news_mascot',
            'metadata.reference_policy' => 'original_sample_character',
            'is_active' => true,
            'sort_order' => 10,
        ];

        foreach ($overrides as $key => $value) {
            $data[$key] = $value;
        }

        /** @var array<string, mixed> $data */
        return $data;
    }
}
