<?php

namespace Database\Factories;

use App\Models\TopicScreeningKeywordRule;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<TopicScreeningKeywordRule>
 */
class TopicScreeningKeywordRuleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'rule_type' => TopicScreeningKeywordRule::TYPE_LIMITATION,
            'keyword' => 'dummy-' . Str::lower(Str::random(8)),
            'match_type' => TopicScreeningKeywordRule::MATCH_CONTAINS,
            'target_fields' => [TopicScreeningKeywordRule::FIELD_LIMITATIONS],
            'penalty' => 30,
            'severity' => 'medium',
            'action' => TopicScreeningKeywordRule::ACTION_FLAG,
            'is_active' => true,
            'sort_order' => 100,
            'notes' => null,
        ];
    }
}
