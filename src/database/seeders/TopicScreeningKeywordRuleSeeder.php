<?php

namespace Database\Seeders;

use App\Models\TopicScreeningKeywordRule;
use Illuminate\Database\Seeder;

/**
 * Topic screening keyword rule の初期マスターデータを投入する Seeder。
 */
class TopicScreeningKeywordRuleSeeder extends Seeder
{
    /**
     * Seed the topic screening keyword rule master data.
     */
    public function run(): void
    {
        $sortOrder = 10;

        foreach ($this->limitationKeywords() as $keyword) {
            TopicScreeningKeywordRule::query()->updateOrCreate(
                [
                    'rule_type' => TopicScreeningKeywordRule::TYPE_LIMITATION,
                    'keyword' => $keyword,
                    'match_type' => TopicScreeningKeywordRule::MATCH_CONTAINS,
                ],
                [
                    'target_fields' => [TopicScreeningKeywordRule::FIELD_LIMITATIONS],
                    'penalty' => 30,
                    'severity' => 'medium',
                    'action' => TopicScreeningKeywordRule::ACTION_FLAG,
                    'is_active' => true,
                    'sort_order' => $sortOrder,
                    'notes' => null,
                ],
            );

            $sortOrder += 10;
        }

        $sortOrder = 10;

        foreach ($this->sensitiveKeywords() as $keyword) {
            TopicScreeningKeywordRule::query()->updateOrCreate(
                [
                    'rule_type' => TopicScreeningKeywordRule::TYPE_SENSITIVE,
                    'keyword' => $keyword,
                    'match_type' => TopicScreeningKeywordRule::MATCH_CONTAINS,
                ],
                [
                    'target_fields' => [
                        TopicScreeningKeywordRule::FIELD_TITLE,
                        TopicScreeningKeywordRule::FIELD_SUMMARY_SEED,
                        TopicScreeningKeywordRule::FIELD_WHY_IT_MATTERS_SEED,
                        TopicScreeningKeywordRule::FIELD_TAGS,
                        TopicScreeningKeywordRule::FIELD_CONTENT_TYPE,
                        TopicScreeningKeywordRule::FIELD_LIMITATIONS,
                    ],
                    'penalty' => null,
                    'severity' => 'medium',
                    'action' => TopicScreeningKeywordRule::ACTION_REJECT,
                    'is_active' => true,
                    'sort_order' => $sortOrder,
                    'notes' => null,
                ],
            );

            $sortOrder += 10;
        }
    }

    /**
     * 初期 limitation keyword 一覧を返す。
     *
     * @return array<int, string>
     */
    private function limitationKeywords(): array
    {
        return [
            'headline only',
            'title only',
            'only a headline',
            'no body',
            'missing body',
            'incomplete',
            'truncated',
            'not independently verified',
            'unverified',
            'speculative',
            'subjective',
            'promotional',
            'landing page',
            'extraction failed',
            'insufficient context',
        ];
    }

    /**
     * 初期 sensitive keyword 一覧を返す。
     *
     * @return array<int, string>
     */
    private function sensitiveKeywords(): array
    {
        return [
            'disaster',
            'accident',
            'crime',
            'war',
            'military',
            'terrorism',
            'politics',
            'election',
            'medical',
            'health',
            'finance',
            'investment',
            'self-harm',
            'sexual',
            'abuse',
            'violence',
            'hate',
            'discrimination',
            'personal data',
            'credential leak',
            'security breach',
            'exploit',
        ];
    }
}
