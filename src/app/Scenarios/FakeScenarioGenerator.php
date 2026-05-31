<?php

namespace App\Scenarios;

use App\Topics\Editorial\TopicEditorialEvaluation;

/**
 * ローカル開発とテスト向けの deterministic な fake scenario generator。
 */
class FakeScenarioGenerator implements ScenarioGenerator
{
    private const TITLE_MAX_LENGTH = 32;

    private const TITLE_SUFFIX = ' ほか';

    private ScenarioTopicSelector $topicSelector;

    private int $maxTopics;

    /**
     * Constructor.
     */
    public function __construct(?ScenarioTopicSelector $topicSelector = null, ?int $maxTopics = null)
    {
        $this->topicSelector = $topicSelector ?? new ScenarioTopicSelector();
        $this->maxTopics = $maxTopics ?? $this->intConfig('radiopipe.scenario.max_topics', 5);
    }

    /**
     * Fake scenario を生成する。
     */
    public function generate(ScenarioGenerationInput $input): ScenarioGenerationResult
    {
        $topicSelections = $this->topicSelector->select($input->editorialEvaluations, $this->maxTopics);
        $usedSelections = array_values(array_filter(
            $topicSelections,
            static fn (ScenarioTopicSelection $selection): bool => $selection->status === ScenarioTopicSelectionStatus::UsedInScenario,
        ));
        $sections = [
            new ScenarioSection(
                type: 'opening',
                title: 'オープニング',
                text: 'さてさて、今日のニュースを一旦見ていきます。',
                topicIds: [],
                estimatedDurationSeconds: null,
                metadata: ['driver' => 'fake'],
            ),
        ];

        foreach ($usedSelections as $selection) {
            $evaluation = $this->evaluationForSelection($input->editorialEvaluations, $selection);

            if (! $evaluation instanceof TopicEditorialEvaluation) {
                continue;
            }

            $sections[] = new ScenarioSection(
                type: 'topic',
                title: $evaluation->localized->title,
                text: sprintf(
                    '次の話題です。%s。%s',
                    $evaluation->localized->title,
                    $evaluation->localized->summary,
                ),
                topicIds: [$selection->topicId],
                estimatedDurationSeconds: null,
                metadata: [
                    'editorial_score' => $evaluation->editorialScore,
                ],
            );
        }

        $sections[] = new ScenarioSection(
            type: 'closing',
            title: 'クロージング',
            text: '今日のニュースはここまでです。気になる話題はあとで詳しく見てみてください。',
            topicIds: [],
            estimatedDurationSeconds: null,
            metadata: ['driver' => 'fake'],
        );

        $scriptText = implode(PHP_EOL . PHP_EOL, array_map(
            static fn (ScenarioSection $section): string => $section->text,
            $sections,
        ));
        $estimatedDurationSeconds = $this->estimateDurationSeconds($scriptText);
        $sections = array_map(
            fn (ScenarioSection $section): ScenarioSection => new ScenarioSection(
                type: $section->type,
                title: $section->title,
                text: $section->text,
                topicIds: $section->topicIds,
                estimatedDurationSeconds: $this->estimateDurationSeconds($section->text),
                metadata: $section->metadata,
            ),
            $sections,
        );

        $scenario = new Scenario(
            title: $this->scenarioTitle($input->editorialEvaluations, $usedSelections),
            language: $input->language,
            targetDurationSeconds: $input->targetDurationSeconds,
            estimatedDurationSeconds: $estimatedDurationSeconds,
            characterKey: $input->characterKey,
            scriptText: $scriptText,
            sections: $sections,
            metadata: [
                'driver' => 'fake',
                'schema_version' => '1.0',
            ],
        );

        return new ScenarioGenerationResult(
            scenario: $scenario,
            topicSelections: $topicSelections,
            metadata: [
                'generator' => 'fake',
                'selected_topic_count' => count($usedSelections),
            ],
        );
    }

    /**
     * @param list<TopicEditorialEvaluation> $editorialEvaluations
     * @param list<ScenarioTopicSelection> $usedSelections
     */
    private function scenarioTitle(array $editorialEvaluations, array $usedSelections): string
    {
        foreach ($usedSelections as $selection) {
            $evaluation = $this->evaluationForSelection($editorialEvaluations, $selection);

            if (! $evaluation instanceof TopicEditorialEvaluation) {
                continue;
            }

            $title = trim($evaluation->localized->title);

            if ($title !== '') {
                return $this->topicAwareTitle($title);
            }
        }

        return '今日のトピック';
    }

    private function topicAwareTitle(string $topicTitle): string
    {
        $normalized = preg_replace('/\s+/u', ' ', trim($topicTitle));
        $base = is_string($normalized) && $normalized !== '' ? $normalized : $topicTitle;
        $suffixLength = mb_strlen(self::TITLE_SUFFIX);
        $ellipsisLength = 1;
        $maxBaseLength = self::TITLE_MAX_LENGTH - $suffixLength;

        if (mb_strlen($base) > $maxBaseLength) {
            $base = rtrim(mb_substr($base, 0, $maxBaseLength - $ellipsisLength), " \t\n\r\0\x0B、。,.・") . '…';
        }

        return $base . self::TITLE_SUFFIX;
    }

    /**
     * @param list<TopicEditorialEvaluation> $editorialEvaluations
     */
    private function evaluationForSelection(array $editorialEvaluations, ScenarioTopicSelection $selection): ?TopicEditorialEvaluation
    {
        foreach ($editorialEvaluations as $index => $evaluation) {
            if ($this->topicSelector->topicId($evaluation, $index) === $selection->topicId) {
                return $evaluation;
            }
        }

        return null;
    }

    private function estimateDurationSeconds(string $text): int
    {
        return max(30, (int) round(mb_strlen($text) / 6));
    }

    private function intConfig(string $key, int $default): int
    {
        $value = config($key, $default);

        return is_int($value) ? $value : $default;
    }
}
