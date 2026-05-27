<?php

namespace App\Models;

use Database\Factories\TopicScreeningKeywordRuleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Topic screening で使う keyword rule のマスターデータ。
 */
#[Fillable([
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
])]
class TopicScreeningKeywordRule extends Model
{
    /** @use HasFactory<TopicScreeningKeywordRuleFactory> */
    use HasFactory;

    public const TYPE_LIMITATION = 'limitation';

    public const TYPE_SENSITIVE = 'sensitive';

    public const MATCH_CONTAINS = 'contains';

    public const ACTION_FLAG = 'flag';

    public const ACTION_REJECT = 'reject';

    public const FIELD_TITLE = 'title';

    public const FIELD_SUMMARY_SEED = 'summary_seed';

    public const FIELD_WHY_IT_MATTERS_SEED = 'why_it_matters_seed';

    public const FIELD_TAGS = 'tags';

    public const FIELD_CONTENT_TYPE = 'content_type';

    public const FIELD_LIMITATIONS = 'limitations';

    /**
     * Active な rule だけに絞る。
     *
     * @param Builder<TopicScreeningKeywordRule> $query
     *
     * @return Builder<TopicScreeningKeywordRule>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * 指定された rule type に絞る。
     *
     * @param Builder<TopicScreeningKeywordRule> $query
     *
     * @return Builder<TopicScreeningKeywordRule>
     */
    public function scopeOfType(Builder $query, string $ruleType): Builder
    {
        return $query->where('rule_type', $ruleType);
    }

    /**
     * 許可された rule type 一覧を返す。
     *
     * @return array<string, string>
     */
    public static function ruleTypeOptions(): array
    {
        return [
            self::TYPE_LIMITATION => 'Limitation',
            self::TYPE_SENSITIVE => 'Sensitive',
        ];
    }

    /**
     * 許可された match type 一覧を返す。
     *
     * @return array<string, string>
     */
    public static function matchTypeOptions(): array
    {
        return [
            self::MATCH_CONTAINS => 'Contains',
        ];
    }

    /**
     * 許可された action 一覧を返す。
     *
     * @return array<string, string>
     */
    public static function actionOptions(): array
    {
        return [
            self::ACTION_FLAG => 'Flag',
            self::ACTION_REJECT => 'Reject',
        ];
    }

    /**
     * 許可された target field 一覧を返す。
     *
     * @return array<string, string>
     */
    public static function targetFieldOptions(): array
    {
        return [
            self::FIELD_TITLE => 'Title',
            self::FIELD_SUMMARY_SEED => 'Summary seed',
            self::FIELD_WHY_IT_MATTERS_SEED => 'Why it matters seed',
            self::FIELD_TAGS => 'Tags',
            self::FIELD_CONTENT_TYPE => 'Content type',
            self::FIELD_LIMITATIONS => 'Limitations',
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
            'target_fields' => 'array',
            'penalty' => 'integer',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
