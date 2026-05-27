<?php

namespace App\Topics\Screening;

use App\Models\TopicScreeningKeywordRule;
use Illuminate\Support\Facades\Log;

/**
 * Topic screening keyword rule を database から読み込む provider。
 */
class TopicScreeningKeywordRuleProvider
{
    /**
     * Active な keyword rule を type ごとに返す。
     *
     * @return array{limitation: list<TopicScreeningKeywordRule>, sensitive: list<TopicScreeningKeywordRule>}
     */
    public function activeRules(): array
    {
        $rules = TopicScreeningKeywordRule::all()
            ->filter(static fn (TopicScreeningKeywordRule $rule): bool => $rule->is_active
                && in_array($rule->rule_type, [
                    TopicScreeningKeywordRule::TYPE_LIMITATION,
                    TopicScreeningKeywordRule::TYPE_SENSITIVE,
                ], true)
                && $rule->match_type === TopicScreeningKeywordRule::MATCH_CONTAINS)
            ->sortBy([
                ['rule_type', 'asc'],
                ['sort_order', 'asc'],
                ['keyword', 'asc'],
            ])
            ->values();

        if ($rules->isEmpty()) {
            Log::warning('No active topic screening keyword rules found. Keyword matching will be skipped.');
        }

        $limitationRules = [];
        $sensitiveRules = [];

        foreach ($rules as $rule) {
            if ($rule->rule_type === TopicScreeningKeywordRule::TYPE_LIMITATION) {
                $limitationRules[] = $rule;
            } elseif ($rule->rule_type === TopicScreeningKeywordRule::TYPE_SENSITIVE) {
                $sensitiveRules[] = $rule;
            }
        }

        return [
            'limitation' => $limitationRules,
            'sensitive' => $sensitiveRules,
        ];
    }
}
