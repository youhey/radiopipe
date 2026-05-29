<?php

namespace App\Scenarios;

use App\Models\CharacterProfile;
use App\Topics\Editorial\TopicEditorialEvaluation;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use JsonException;
use stdClass;
use ValueError;

/**
 * OpenAI Responses API を使う Scenario Generator。
 */
class OpenAiScenarioGenerator implements ScenarioGenerator
{
    private string $apiKey;

    private string $model;

    private int $timeout;

    private int $maxRetries;

    private ScenarioTopicSelector $topicSelector;

    private CharacterProfileInstructionBuilder $instructionBuilder;

    private int $maxTopics;

    /**
     * Constructor.
     */
    public function __construct(
        ?string $apiKey,
        string $model,
        int $timeout,
        int $maxRetries,
        ?ScenarioTopicSelector $topicSelector = null,
        ?CharacterProfileInstructionBuilder $instructionBuilder = null,
        int $maxTopics = 5,
    ) {
        if ($apiKey === null || trim($apiKey) === '') {
            throw new InvalidArgumentException('OPENAI_API_KEY must be configured when scenario generator [openai] is selected.');
        }

        $this->apiKey = $apiKey;
        $this->model = $model;
        $this->timeout = $timeout;
        $this->maxRetries = $maxRetries;
        $this->topicSelector = $topicSelector ?? new ScenarioTopicSelector();
        $this->instructionBuilder = $instructionBuilder ?? new CharacterProfileInstructionBuilder();
        $this->maxTopics = $maxTopics;
    }

    /**
     * OpenAI で structured scenario を生成する。
     *
     * @throws ConnectionException
     * @throws JsonException
     */
    public function generate(ScenarioGenerationInput $input): ScenarioGenerationResult
    {
        $characterProfile = $this->characterProfile($input->characterKey);
        $topicSelections = $this->topicSelector->select($input->editorialEvaluations, $this->maxTopics);
        $usedSelections = array_values(array_filter(
            $topicSelections,
            static fn (ScenarioTopicSelection $selection): bool => $selection->status === ScenarioTopicSelectionStatus::UsedInScenario,
        ));
        $selectedTopicIds = array_map(
            static fn (ScenarioTopicSelection $selection): string => $selection->topicId,
            $usedSelections,
        );

        $response = Http::baseUrl('https://api.openai.com')
            ->withToken($this->apiKey)
            ->acceptJson()
            ->asJson()
            ->timeout($this->timeout)
            ->retry($this->maxRetries, 100, null, false)
            ->post('/v1/responses', $this->requestPayload($input, $characterProfile, $usedSelections));

        if ($response->failed()) {
            throw ScenarioGeneratorException::failedHttpResponse('openai', $response->status(), $this->errorDetailsFromResponse($response));
        }

        $payload = $response->json();

        if (! is_array($payload)) {
            throw ScenarioGeneratorException::invalidResponse('openai');
        }

        /** @var array<string, mixed> $payload */
        $text = $this->extractOutputText($payload);
        $decoded = json_decode($text, true);

        if (! is_array($decoded)) {
            throw ScenarioGeneratorException::invalidResponse('openai', 'invalid JSON');
        }

        /** @var array<string, mixed> $decoded */
        $scenario = $this->scenarioFromArray($decoded, $selectedTopicIds);

        return new ScenarioGenerationResult(
            scenario: $scenario,
            topicSelections: $topicSelections,
            metadata: [
                'generator' => 'openai',
                'model' => $this->model,
                'selected_topic_count' => count($usedSelections),
            ],
        );
    }

    /**
     * @param list<ScenarioTopicSelection> $usedSelections
     *
     * @return array<string, mixed>
     *
     * @throws JsonException
     */
    private function requestPayload(ScenarioGenerationInput $input, CharacterProfile $characterProfile, array $usedSelections): array
    {
        return [
            'model' => $this->model,
            'instructions' => $this->instructions($characterProfile),
            'input' => [
                [
                    'role' => 'user',
                    'content' => [
                        [
                            'type' => 'input_text',
                            'text' => json_encode($this->scenarioInput($input, $usedSelections), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                        ],
                    ],
                ],
            ],
            'text' => [
                'format' => [
                    'type' => 'json_schema',
                    'name' => 'radio_scenario',
                    'strict' => true,
                    'schema' => $this->responseSchema(),
                ],
            ],
        ];
    }

    private function instructions(CharacterProfile $characterProfile): string
    {
        return implode("\n", [
            'You are writing a Japanese radio-style news scenario.',
            'Use the provided character profile for voice and style, but character style must never override factual constraints.',
            'Use only the provided topic facts.',
            'Do not invent facts, dates, numbers, names, quotes, source details, or article contents.',
            'Preserve uncertainty from topic limitations, flags, and editorial notes.',
            'Do not treat uncertain source material as confirmed fact.',
            'Do not joke about disasters, crimes, deaths, abuse, security incidents, or serious harm.',
            'Do not provide investment, medical, legal, safety, or security remediation advice.',
            'Respect banned phrases and disallowed expressions.',
            'Keep serious topics restrained and neutral.',
            'Use only topics listed in selected_topics.',
            'Do not include skipped topics in the spoken scenario unless explicitly requested.',
            'Generate structured JSON only.',
            'Match the required schema exactly.',
            $this->instructionBuilder->build($characterProfile),
        ]);
    }

    /**
     * @param list<ScenarioTopicSelection> $usedSelections
     *
     * @return array<string, mixed>
     */
    private function scenarioInput(ScenarioGenerationInput $input, array $usedSelections): array
    {
        return [
            'title' => $input->title ?? '今日のギークニュース',
            'language' => $input->language,
            'target_duration_seconds' => $input->targetDurationSeconds,
            'character_key' => $input->characterKey,
            'selected_topics' => array_map(
                fn (ScenarioTopicSelection $selection): array => $this->topicInput($input->editorialEvaluations, $selection),
                $usedSelections,
            ),
        ];
    }

    /**
     * @param list<TopicEditorialEvaluation> $editorialEvaluations
     *
     * @return array<string, mixed>
     */
    private function topicInput(array $editorialEvaluations, ScenarioTopicSelection $selection): array
    {
        $evaluation = $this->evaluationForSelection($editorialEvaluations, $selection);

        if (! $evaluation instanceof TopicEditorialEvaluation) {
            throw ScenarioGeneratorException::invalidResponse('openai', 'selected topic is missing from editorial evaluations');
        }

        return [
            'topic_id' => $selection->topicId,
            'localized' => $evaluation->localized->toArray(),
            'editorial_score' => $evaluation->editorialScore,
            'scores' => $evaluation->scores->toArray(),
            'flags' => $evaluation->flags->toArray(),
            'scenario_notes' => $evaluation->scenarioNotes->toArray(),
            'source_name' => $this->nullableMetadataString($evaluation, 'source_name'),
            'url' => $this->nullableMetadataString($evaluation, 'url'),
            'discussion_url' => $this->nullableMetadataString($evaluation, 'discussion_url'),
            'limitations' => $this->nullableMetadataString($evaluation, 'limitations'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function responseSchema(): array
    {
        $duration = ['type' => ['integer', 'null'], 'minimum' => 0];
        $metadata = [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['schema_version', 'driver'],
            'properties' => [
                'schema_version' => ['type' => 'string'],
                'driver' => ['type' => 'string', 'enum' => ['openai']],
            ],
        ];

        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['title', 'language', 'target_duration_seconds', 'estimated_duration_seconds', 'character_key', 'script_text', 'sections', 'metadata'],
            'properties' => [
                'title' => ['type' => 'string'],
                'language' => ['type' => 'string'],
                'target_duration_seconds' => ['type' => 'integer', 'minimum' => 0],
                'estimated_duration_seconds' => $duration,
                'character_key' => ['type' => ['string', 'null']],
                'script_text' => ['type' => 'string'],
                'sections' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'required' => ['type', 'title', 'text', 'topic_ids', 'estimated_duration_seconds', 'metadata'],
                        'properties' => [
                            'type' => ['type' => 'string', 'enum' => ['opening', 'topic', 'closing']],
                            'title' => ['type' => 'string'],
                            'text' => ['type' => 'string'],
                            'topic_ids' => [
                                'type' => 'array',
                                'items' => ['type' => 'string'],
                            ],
                            'estimated_duration_seconds' => $duration,
                            'metadata' => [
                                'type' => 'object',
                                'additionalProperties' => false,
                                'required' => [],
                                'properties' => new stdClass(),
                            ],
                        ],
                    ],
                ],
                'metadata' => $metadata,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function extractOutputText(array $payload): string
    {
        $outputText = $payload['output_text'] ?? null;

        if (is_string($outputText) && trim($outputText) !== '') {
            return $outputText;
        }

        $output = $payload['output'] ?? null;

        if (! is_array($output)) {
            throw ScenarioGeneratorException::invalidResponse('openai', 'missing output text');
        }

        foreach ($output as $item) {
            if (! is_array($item)) {
                continue;
            }

            $content = $item['content'] ?? null;

            if (! is_array($content)) {
                continue;
            }

            foreach ($content as $contentItem) {
                if (! is_array($contentItem)) {
                    continue;
                }

                $text = $contentItem['text'] ?? null;

                if (($contentItem['type'] ?? null) === 'output_text' && is_string($text) && trim($text) !== '') {
                    return $text;
                }
            }
        }

        throw ScenarioGeneratorException::invalidResponse('openai', 'missing output text');
    }

    /**
     * @param array<string, mixed> $payload
     * @param list<string> $selectedTopicIds
     */
    private function scenarioFromArray(array $payload, array $selectedTopicIds): Scenario
    {
        try {
            $scriptText = $this->stringValue($payload, 'script_text');
            $sections = $this->sectionsFromArray($this->listValue($payload, 'sections'), $selectedTopicIds);

            if (trim($scriptText) === '') {
                throw ScenarioGeneratorException::invalidResponse('openai', 'script_text must not be empty');
            }

            if ($sections === []) {
                throw ScenarioGeneratorException::invalidResponse('openai', 'sections must not be empty');
            }

            return new Scenario(
                title: $this->stringValue($payload, 'title'),
                language: $this->stringValue($payload, 'language'),
                targetDurationSeconds: $this->intValue($payload, 'target_duration_seconds'),
                estimatedDurationSeconds: $this->nullableIntValue($payload, 'estimated_duration_seconds'),
                characterKey: $this->nullableStringValue($payload, 'character_key'),
                scriptText: $scriptText,
                sections: $sections,
                metadata: $this->arrayValue($payload, 'metadata'),
            );
        } catch (InvalidArgumentException|ValueError $exception) {
            throw ScenarioGeneratorException::invalidResponse('openai', $exception->getMessage());
        }
    }

    /**
     * @param list<mixed> $payload
     * @param list<string> $selectedTopicIds
     *
     * @return list<ScenarioSection>
     */
    private function sectionsFromArray(array $payload, array $selectedTopicIds): array
    {
        $sections = [];

        foreach ($payload as $sectionPayload) {
            if (! is_array($sectionPayload)) {
                throw ScenarioGeneratorException::invalidResponse('openai', 'section must be an object');
            }

            /** @var array<string, mixed> $sectionPayload */
            $type = $this->stringValue($sectionPayload, 'type');

            if (! in_array($type, ['opening', 'topic', 'closing'], true)) {
                throw ScenarioGeneratorException::invalidResponse('openai', "unsupported section type [{$type}]");
            }

            $topicIds = $this->stringListValue($sectionPayload, 'topic_ids');

            foreach ($topicIds as $topicId) {
                if (! in_array($topicId, $selectedTopicIds, true)) {
                    throw ScenarioGeneratorException::invalidResponse('openai', "unknown topic id [{$topicId}]");
                }
            }

            $sections[] = new ScenarioSection(
                type: $type,
                title: $this->stringValue($sectionPayload, 'title'),
                text: $this->stringValue($sectionPayload, 'text'),
                topicIds: $topicIds,
                estimatedDurationSeconds: $this->nullableIntValue($sectionPayload, 'estimated_duration_seconds'),
                metadata: $this->arrayValue($sectionPayload, 'metadata'),
            );
        }

        return $sections;
    }

    private function characterProfile(?string $characterKey): CharacterProfile
    {
        if ($characterKey === null || trim($characterKey) === '') {
            throw new InvalidArgumentException('character_key must be configured for scenario generator [openai].');
        }

        $profile = CharacterProfile::query()
            ->where('character_key', $characterKey)
            ->where('is_active', true)
            ->first();

        if (! $profile instanceof CharacterProfile) {
            throw new InvalidArgumentException("Active character profile [{$characterKey}] was not found.");
        }

        return $profile;
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

    /**
     * OpenAI Error Object から公開してよい最小限の詳細だけを取り出して返す。
     *
     * @return array{message?: string, type?: string, code?: string}
     */
    private function errorDetailsFromResponse(Response $response): array
    {
        $payload = $response->json();

        if (! is_array($payload)) {
            return [];
        }

        $error = $payload['error'] ?? null;

        if (! is_array($error)) {
            return [];
        }

        $details = [];

        foreach (['message', 'type', 'code'] as $key) {
            $value = $error[$key] ?? null;

            if (is_string($value) && trim($value) !== '') {
                $details[$key] = $value;
            }
        }

        return $details;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function stringValue(array $payload, string $key): string
    {
        $value = $payload[$key] ?? null;

        if (! is_string($value)) {
            throw ScenarioGeneratorException::invalidResponse('openai', "missing string field [{$key}]");
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function nullableStringValue(array $payload, string $key): ?string
    {
        $value = $payload[$key] ?? null;

        if ($value !== null && ! is_string($value)) {
            throw ScenarioGeneratorException::invalidResponse('openai', "invalid nullable string field [{$key}]");
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function intValue(array $payload, string $key): int
    {
        $value = $payload[$key] ?? null;

        if (! is_int($value)) {
            throw ScenarioGeneratorException::invalidResponse('openai', "missing integer field [{$key}]");
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function nullableIntValue(array $payload, string $key): ?int
    {
        $value = $payload[$key] ?? null;

        if ($value !== null && ! is_int($value)) {
            throw ScenarioGeneratorException::invalidResponse('openai', "invalid nullable integer field [{$key}]");
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    private function arrayValue(array $payload, string $key): array
    {
        $value = $payload[$key] ?? null;

        if (! is_array($value)) {
            throw ScenarioGeneratorException::invalidResponse('openai', "missing object field [{$key}]");
        }

        /** @var array<string, mixed> $value */
        return $value;
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return list<mixed>
     */
    private function listValue(array $payload, string $key): array
    {
        $value = $payload[$key] ?? null;

        if (! is_array($value) || ! array_is_list($value)) {
            throw ScenarioGeneratorException::invalidResponse('openai', "missing list field [{$key}]");
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return list<string>
     */
    private function stringListValue(array $payload, string $key): array
    {
        $value = $this->listValue($payload, $key);
        $strings = [];

        foreach ($value as $item) {
            if (! is_string($item)) {
                throw ScenarioGeneratorException::invalidResponse('openai', "invalid string list field [{$key}]");
            }

            $strings[] = $item;
        }

        return $strings;
    }

    private function nullableMetadataString(TopicEditorialEvaluation $evaluation, string $key): ?string
    {
        $value = $evaluation->metadata[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }
}
