<?php

namespace App\Models;

use Database\Factories\CharacterProfileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * シナリオ生成に使うキャラクター人格のマスターデータ。
 */
#[Fillable([
    'character_key',
    'name',
    'role',
    'personality',
    'tone',
    'speech_style',
    'catchphrases',
    'style_examples',
    'banned_phrases',
    'disallowed_expressions',
    'serious_topic_behavior',
    'content_policy',
    'script_preferences',
    'metadata',
    'is_active',
    'sort_order',
])]
class CharacterProfile extends Model
{
    /** @use HasFactory<CharacterProfileFactory> */
    use HasFactory;

    /**
     * シナリオ生成向けの構造化プロフィールとして出力する。
     *
     * @return array<string, mixed>
     */
    public function toScenarioProfileArray(): array
    {
        $metadata = $this->getAttribute('metadata');

        return [
            'character_key' => $this->character_key,
            'name' => $this->name,
            'role' => $this->role,
            'personality' => $this->personality,
            'tone' => $this->tone,
            'speech_style' => $this->speech_style,
            'catchphrases' => $this->catchphrases,
            'style_examples' => $this->style_examples,
            'banned_phrases' => $this->banned_phrases,
            'disallowed_expressions' => $this->disallowed_expressions,
            'serious_topic_behavior' => $this->serious_topic_behavior,
            'content_policy' => $this->content_policy,
            'script_preferences' => $this->script_preferences,
            'metadata' => self::withFixedMetadata(self::stringKeyedArray($metadata)),
            'is_active' => $this->is_active,
            'sort_order' => $this->sort_order,
        ];
    }

    /**
     * 固定メタデータを含めた metadata を返す。
     *
     * @param array<string, mixed>|null $metadata
     *
     * @return array<string, mixed>
     */
    public static function withFixedMetadata(?array $metadata): array
    {
        return [
            'schema_version' => '1.0',
            'created_for' => 'radiopipe',
            'intended_use' => 'Scenario generation instructions',
            'direction' => $metadata['direction'] ?? 'sample_safe_news_mascot',
            'reference_policy' => $metadata['reference_policy'] ?? 'original_sample_character',
        ];
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'speech_style' => 'array',
            'catchphrases' => 'array',
            'style_examples' => 'array',
            'banned_phrases' => 'array',
            'disallowed_expressions' => 'array',
            'serious_topic_behavior' => 'array',
            'content_policy' => 'array',
            'script_preferences' => 'array',
            'metadata' => 'array',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * 文字列キーの配列として扱える値だけを抽出する。
     *
     * @return array<string, mixed>
     */
    private static function stringKeyedArray(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $result = [];

        foreach ($value as $key => $item) {
            if (is_string($key)) {
                $result[$key] = $item;
            }
        }

        return $result;
    }
}
