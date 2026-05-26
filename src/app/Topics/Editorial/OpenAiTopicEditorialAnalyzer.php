<?php

namespace App\Topics\Editorial;

use App\Topics\TopicDraft;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use ValueError;

/**
 * OpenAI Responses API を使う Topic Editorial Analyzer です。
 */
class OpenAiTopicEditorialAnalyzer implements TopicEditorialAnalyzer
{
    private string $apiKey;

    private string $model;

    private int $timeout;

    private int $maxRetries;

    /**
     * Constructor.
     */
    public function __construct(?string $apiKey, string $model, int $timeout, int $maxRetries)
    {
        if ($apiKey === null || trim($apiKey) === '') {
            throw new InvalidArgumentException('OPENAI_API_KEY must be configured when topic editorial analyzer [openai] is selected.');
        }

        $this->apiKey = $apiKey;
        $this->model = $model;
        $this->timeout = $timeout;
        $this->maxRetries = $maxRetries;
    }

    /**
     * TopicDraft を OpenAI により editorial evaluation として解析します。
     */
    public function analyze(TopicDraft $topicDraft): TopicEditorialEvaluation
    {
        $response = Http::baseUrl('https://api.openai.com')
            ->withToken($this->apiKey)
            ->acceptJson()
            ->asJson()
            ->timeout($this->timeout)
            ->retry($this->maxRetries, 100, null, false)
            ->post('/v1/responses', $this->requestPayload($topicDraft));

        if ($response->failed()) {
            throw TopicEditorialAnalyzerException::failedHttpResponse('openai', $response->status(), $this->errorDetailsFromResponse($response));
        }

        $payload = $response->json();

        if (! is_array($payload)) {
            throw TopicEditorialAnalyzerException::invalidResponse('openai');
        }

        /** @var array<string, mixed> $payload */
        $text = $this->extractOutputText($payload);
        $decoded = json_decode($text, true);

        if (! is_array($decoded)) {
            throw TopicEditorialAnalyzerException::invalidResponse('openai');
        }

        /** @var array<string, mixed> $decoded */
        return $this->evaluationFromArray($decoded);
    }

    /**
     * OpenAI error object から公開してよい最小限の詳細だけを取り出します。
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
     * Responses API request payload を作成します。
     *
     * @return array<string, mixed>
     */
    private function requestPayload(TopicDraft $topicDraft): array
    {
        return [
            'model' => $this->model,
            'instructions' => $this->instructions(),
            'input' => [
                [
                    'role' => 'user',
                    'content' => [
                        [
                            'type' => 'input_text',
                            'text' => json_encode($this->topicInput($topicDraft), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                        ],
                    ],
                ],
            ],
            'text' => [
                'format' => [
                    'type' => 'json_schema',
                    'name' => 'topic_editorial_evaluation',
                    'strict' => true,
                    'schema' => $this->responseSchema(),
                ],
            ],
        ];
    }

    private function instructions(): string
    {
        return implode("\n", [
            'You are evaluating a topic for a personalized Japanese radio-style news script.',
            'Localize the topic into natural Japanese, summarize it concisely, and evaluate editorial value in one response.',
            'Evaluate likely technical-interest fit, general importance, certainty, sensitivity, scenario fitness, and flow hints.',
            'Detect likely semantic duplicate candidates only when evidence is strong; weak candidates should remain pending.',
            'Do not invent facts that are not supported by the structured input.',
            'Do not treat uncertain or partial source material as confirmed fact.',
            'Do not make investment, medical, legal, or safety advice.',
            'Do not joke about disasters, crimes, deaths, abuse, or serious harm.',
            'Choose status from pending, skipped_low_value, skipped_duplicate, skipped_uncertain, skipped_sensitive.',
            'pending means the topic may proceed to later Scenario Topic Selection; it does not mean used_in_scenario.',
            'Return only structured JSON matching the schema.',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function topicInput(TopicDraft $topicDraft): array
    {
        return [
            'id' => $topicDraft->id,
            'source_type' => $topicDraft->sourceType,
            'source_name' => $topicDraft->sourceName,
            'title' => $topicDraft->title,
            'url' => $topicDraft->url,
            'discussion_url' => $topicDraft->discussionUrl,
            'summary_seed' => $topicDraft->summarySeed,
            'why_it_matters_seed' => $topicDraft->whyItMattersSeed,
            'tags' => $topicDraft->tags,
            'entities' => $topicDraft->entities,
            'importance' => $topicDraft->importance,
            'confidence' => $topicDraft->confidence,
            'content_type' => $topicDraft->contentType,
            'limitations' => $topicDraft->limitations,
            'published_at' => $topicDraft->publishedAt?->toJSON(),
            'fetched_at' => $topicDraft->fetchedAt?->toJSON(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function responseSchema(): array
    {
        $score = ['type' => 'integer', 'minimum' => 0, 'maximum' => 100];
        $nullableString = ['type' => ['string', 'null']];
        $stringList = [
            'type' => 'array',
            'items' => ['type' => 'string'],
        ];

        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['status', 'editorial_score', 'localized', 'scores', 'flags', 'duplicate', 'scenario_notes', 'reasons', 'metadata'],
            'properties' => [
                'status' => ['type' => 'string', 'enum' => ['pending', 'skipped_low_value', 'skipped_duplicate', 'skipped_uncertain', 'skipped_sensitive']],
                'editorial_score' => $score,
                'localized' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'required' => ['title', 'summary', 'why_it_matters'],
                    'properties' => [
                        'title' => ['type' => 'string'],
                        'summary' => ['type' => 'string'],
                        'why_it_matters' => ['type' => 'string'],
                    ],
                ],
                'scores' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'required' => ['preference', 'general_importance', 'freshness', 'certainty', 'scenario_fitness', 'flow_flexibility'],
                    'properties' => [
                        'preference' => $score,
                        'general_importance' => $score,
                        'freshness' => $score,
                        'certainty' => $score,
                        'scenario_fitness' => $score,
                        'flow_flexibility' => $score,
                    ],
                ],
                'flags' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'required' => ['is_duplicate_candidate', 'is_uncertain', 'is_sensitive'],
                    'properties' => [
                        'is_duplicate_candidate' => ['type' => 'boolean'],
                        'is_uncertain' => ['type' => 'boolean'],
                        'is_sensitive' => ['type' => 'boolean'],
                    ],
                ],
                'duplicate' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'required' => ['canonical_key', 'similar_topic_ids', 'duplicate_of', 'confidence', 'reason'],
                    'properties' => [
                        'canonical_key' => $nullableString,
                        'similar_topic_ids' => $stringList,
                        'duplicate_of' => $nullableString,
                        'confidence' => ['type' => ['integer', 'null'], 'minimum' => 0, 'maximum' => 100],
                        'reason' => $nullableString,
                    ],
                ],
                'scenario_notes' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'required' => ['suggested_role', 'tone', 'transition_hint', 'avoid'],
                    'properties' => [
                        'suggested_role' => $nullableString,
                        'tone' => $nullableString,
                        'transition_hint' => $nullableString,
                        'avoid' => $stringList,
                    ],
                ],
                'reasons' => $stringList,
                'metadata' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'required' => ['schema_version'],
                    'properties' => [
                        'schema_version' => ['type' => 'string'],
                    ],
                ],
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
            throw TopicEditorialAnalyzerException::invalidResponse('openai');
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

        throw TopicEditorialAnalyzerException::invalidResponse('openai');
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function evaluationFromArray(array $payload): TopicEditorialEvaluation
    {
        try {
            $status = TopicEditorialStatus::from($this->stringValue($payload, 'status'));

            return new TopicEditorialEvaluation(
                status: $status,
                editorialScore: $this->intValue($payload, 'editorial_score'),
                localized: $this->localizedFromArray($this->arrayValue($payload, 'localized')),
                scores: $this->scoresFromArray($this->arrayValue($payload, 'scores')),
                flags: $this->flagsFromArray($this->arrayValue($payload, 'flags')),
                duplicate: $this->duplicateFromArray($this->arrayValue($payload, 'duplicate')),
                scenarioNotes: $this->scenarioNotesFromArray($this->arrayValue($payload, 'scenario_notes')),
                reasons: $this->stringListValue($payload, 'reasons'),
                metadata: $this->arrayValue($payload, 'metadata'),
            );
        } catch (InvalidArgumentException|ValueError $exception) {
            throw TopicEditorialAnalyzerException::invalidResponse('openai');
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function localizedFromArray(array $payload): TopicLocalizedText
    {
        return new TopicLocalizedText(
            title: $this->stringValue($payload, 'title'),
            summary: $this->stringValue($payload, 'summary'),
            whyItMatters: $this->stringValue($payload, 'why_it_matters'),
        );
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function scoresFromArray(array $payload): TopicEditorialScores
    {
        return new TopicEditorialScores(
            preference: $this->intValue($payload, 'preference'),
            generalImportance: $this->intValue($payload, 'general_importance'),
            freshness: $this->intValue($payload, 'freshness'),
            certainty: $this->intValue($payload, 'certainty'),
            scenarioFitness: $this->intValue($payload, 'scenario_fitness'),
            flowFlexibility: $this->intValue($payload, 'flow_flexibility'),
        );
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function flagsFromArray(array $payload): TopicEditorialFlags
    {
        return new TopicEditorialFlags(
            isDuplicateCandidate: $this->boolValue($payload, 'is_duplicate_candidate'),
            isUncertain: $this->boolValue($payload, 'is_uncertain'),
            isSensitive: $this->boolValue($payload, 'is_sensitive'),
        );
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function duplicateFromArray(array $payload): TopicDuplicateAssessment
    {
        return new TopicDuplicateAssessment(
            canonicalKey: $this->nullableStringValue($payload, 'canonical_key'),
            similarTopicIds: $this->stringListValue($payload, 'similar_topic_ids'),
            duplicateOf: $this->nullableStringValue($payload, 'duplicate_of'),
            confidence: $this->nullableIntValue($payload, 'confidence'),
            reason: $this->nullableStringValue($payload, 'reason'),
        );
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function scenarioNotesFromArray(array $payload): TopicScenarioNotes
    {
        return new TopicScenarioNotes(
            suggestedRole: $this->nullableStringValue($payload, 'suggested_role'),
            tone: $this->nullableStringValue($payload, 'tone'),
            transitionHint: $this->nullableStringValue($payload, 'transition_hint'),
            avoid: $this->stringListValue($payload, 'avoid'),
        );
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function stringValue(array $payload, string $key): string
    {
        $value = $payload[$key] ?? null;

        if (! is_string($value)) {
            throw TopicEditorialAnalyzerException::invalidResponse('openai');
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
            throw TopicEditorialAnalyzerException::invalidResponse('openai');
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
            throw TopicEditorialAnalyzerException::invalidResponse('openai');
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
            throw TopicEditorialAnalyzerException::invalidResponse('openai');
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function boolValue(array $payload, string $key): bool
    {
        $value = $payload[$key] ?? null;

        if (! is_bool($value)) {
            throw TopicEditorialAnalyzerException::invalidResponse('openai');
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
            throw TopicEditorialAnalyzerException::invalidResponse('openai');
        }

        /** @var array<string, mixed> $value */
        return $value;
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return list<string>
     */
    private function stringListValue(array $payload, string $key): array
    {
        $value = $payload[$key] ?? null;

        if (! is_array($value)) {
            throw TopicEditorialAnalyzerException::invalidResponse('openai');
        }

        $strings = [];

        foreach ($value as $item) {
            if (! is_string($item)) {
                throw TopicEditorialAnalyzerException::invalidResponse('openai');
            }

            $strings[] = $item;
        }

        return $strings;
    }
}
