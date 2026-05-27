<?php

namespace Database\Factories;

use App\Models\CharacterProfile;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CharacterProfile>
 */
class CharacterProfileFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $key = 'dummy_' . Str::lower(Str::random(8));

        return [
            'character_key' => $key,
            'name' => 'ダミーキャラクター',
            'role' => 'テスト用の架空ラジオパーソナリティ',
            'personality' => '公開テスト用の架空プロフィールです。',
            'tone' => '中立で読みやすい',
            'speech_style' => [
                'language' => 'ja',
                'sentence_length' => 'short_to_medium',
                'uses_listener_address' => true,
                'listener_name' => 'キミ',
                'first_person' => 'わたし',
                'ending_style' => '読みやすい口調',
                'pace' => '短い区切りを多めにする',
                'rhythm' => '要点を整理する',
            ],
            'catchphrases' => ['さてさて'],
            'style_examples' => ['さてさて、今日の話題です。'],
            'banned_phrases' => ['絶対に安全です'],
            'disallowed_expressions' => ['未確認情報を断定する'],
            'serious_topic_behavior' => [
                'tone' => '抑制的で中立',
                'allow_jokes' => false,
                'required_style' => '事実と未確認点を分ける',
                'catchphrase_limit' => '原則0個',
                'applies_to' => ['災害'],
            ],
            'content_policy' => [
                'factuality_priority' => '事実正確性を優先する',
                'uncertainty_handling' => '不確実な内容は限定的に扱う',
                'source_limitations' => 'limitations を反映する',
                'no_fabrication' => true,
                'identity_safety' => '架空サンプルです',
            ],
            'script_preferences' => [
                'opening_style' => '短く入る',
                'transition_style' => '短いブリッジを入れる',
                'closing_style' => '要点を振り返る',
                'preferred_segment_roles' => ['main_story'],
            ],
            'metadata' => CharacterProfile::withFixedMetadata([]),
            'is_active' => true,
            'sort_order' => 100,
        ];
    }
}
