<?php

namespace Tests\Unit\Topics\Editorial;

use App\Topics\Editorial\FakeTopicEditorialAnalyzer;
use App\Topics\Editorial\OpenAiTopicEditorialAnalyzer;
use App\Topics\Editorial\TopicEditorialAnalyzer;
use App\Topics\Editorial\TopicEditorialAnalyzerException;
use App\Topics\Editorial\TopicEditorialEvaluation;
use App\Topics\Editorial\TopicEditorialStatus;
use App\Topics\TopicDraft;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * @internal
 */
class OpenAiTopicEditorialAnalyzerTest extends TestCase
{
    public function testItSendsResponsesApiRequestAndMapsValidStructuredResponse(): void
    {
        Http::fake([
            'https://api.openai.com/v1/responses' => Http::response($this->openAiResponse($this->validEvaluationPayload()), 200),
        ]);

        $evaluation = $this->analyzer()->analyze($this->draft());

        self::assertInstanceOf(TopicEditorialEvaluation::class, $evaluation);
        self::assertSame(TopicEditorialStatus::Pending, $evaluation->status);
        self::assertSame(86, $evaluation->editorialScore);
        self::assertSame('AIチップの部品コストでHBMの比率が上昇', $evaluation->localized->title);
        self::assertSame('top_story', $evaluation->scenarioNotes->suggestedRole);

        Http::assertSent(function (Request $request): bool {
            $payload = $request->data();
            $text = $payload['text'] ?? null;
            $format = is_array($text) ? ($text['format'] ?? null) : null;
            $schema = is_array($format) ? ($format['schema'] ?? null) : null;
            $properties = is_array($schema) ? ($schema['properties'] ?? null) : null;
            $metadata = is_array($properties) ? ($properties['metadata'] ?? null) : null;
            $instructions = $payload['instructions'] ?? null;

            return $request->url() === 'https://api.openai.com/v1/responses'
                && $request->hasHeader('Authorization', 'Bearer test-openai-key')
                && ($payload['model'] ?? null) === 'gpt-test'
                && is_array($format)
                && ($format['type'] ?? null) === 'json_schema'
                && ($format['name'] ?? null) === 'topic_editorial_evaluation'
                && is_array($metadata)
                && ($metadata['additionalProperties'] ?? null) === false
                && is_string($instructions)
                && str_contains($instructions, 'Return only structured JSON');
        });
    }

    public function testItRejectsInvalidStatus(): void
    {
        $payload = $this->validEvaluationPayload();
        $payload['status'] = 'used_in_scenario';

        Http::fake([
            'https://api.openai.com/v1/responses' => Http::response($this->openAiResponse($payload), 200),
        ]);

        $this->expectException(TopicEditorialAnalyzerException::class);

        $this->analyzer()->analyze($this->draft());
    }

    public function testItRejectsInvalidScoreRanges(): void
    {
        $payload = $this->validEvaluationPayload();
        $scores = $payload['scores'];
        self::assertIsArray($scores);
        $scores['certainty'] = 101;
        $payload['scores'] = $scores;

        Http::fake([
            'https://api.openai.com/v1/responses' => Http::response($this->openAiResponse($payload), 200),
        ]);

        $this->expectException(TopicEditorialAnalyzerException::class);

        $this->analyzer()->analyze($this->draft());
    }

    public function testItHandlesMissingApiKeyWhenOpenAiDriverIsSelected(): void
    {
        config([
            'radiopipe.topic_editorial.analyzer' => 'openai',
            'radiopipe.openai.api_key' => null,
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('OPENAI_API_KEY must be configured');

        $this->app->make(TopicEditorialAnalyzer::class);
    }

    public function testItDoesNotRequireRealNetworkAccess(): void
    {
        Http::fake([
            'https://api.openai.com/v1/responses' => Http::response($this->openAiResponse($this->validEvaluationPayload()), 200),
        ]);

        $this->analyzer()->analyze($this->draft());

        Http::assertSentCount(1);
    }

    public function testServiceBindingResolvesFakeByDefault(): void
    {
        config(['radiopipe.topic_editorial.analyzer' => 'fake']);

        self::assertInstanceOf(
            FakeTopicEditorialAnalyzer::class,
            $this->app->make(TopicEditorialAnalyzer::class),
        );
    }

    public function testServiceBindingResolvesOpenAiWhenConfigured(): void
    {
        config([
            'radiopipe.topic_editorial.analyzer' => 'openai',
            'radiopipe.topic_editorial.model' => 'gpt-test',
            'radiopipe.openai.api_key' => 'test-openai-key',
        ]);

        self::assertInstanceOf(
            OpenAiTopicEditorialAnalyzer::class,
            $this->app->make(TopicEditorialAnalyzer::class),
        );
    }

    public function testInvalidDriverConfigFailsClearly(): void
    {
        config(['radiopipe.topic_editorial.analyzer' => 'invalid']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported radiopipe topic editorial analyzer [invalid].');

        $this->app->make(TopicEditorialAnalyzer::class);
    }

    public function testHttpFailureFailsClearly(): void
    {
        Http::fake([
            'https://api.openai.com/v1/responses' => Http::response([
                'error' => [
                    'message' => 'Invalid schema for response format.',
                    'type' => 'invalid_request_error',
                    'code' => 'invalid_json_schema',
                ],
            ], 400),
        ]);

        $this->expectException(TopicEditorialAnalyzerException::class);
        $this->expectExceptionMessage('HTTP status [400]');
        $this->expectExceptionMessage('error.message [Invalid schema for response format.]');
        $this->expectExceptionMessage('error.type [invalid_request_error]');
        $this->expectExceptionMessage('error.code [invalid_json_schema]');

        $this->analyzer()->analyze($this->draft());
    }

    public function testInvalidJsonFailsClearly(): void
    {
        Http::fake([
            'https://api.openai.com/v1/responses' => Http::response([
                'output' => [
                    [
                        'type' => 'message',
                        'content' => [
                            [
                                'type' => 'output_text',
                                'text' => '{not-json',
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $this->expectException(TopicEditorialAnalyzerException::class);

        $this->analyzer()->analyze($this->draft());
    }

    private function analyzer(): OpenAiTopicEditorialAnalyzer
    {
        return new OpenAiTopicEditorialAnalyzer(
            apiKey: 'test-openai-key',
            model: 'gpt-test',
            timeout: 60,
            maxRetries: 0,
        );
    }

    /**
     * @param array<string, mixed> $evaluation
     *
     * @return array<string, mixed>
     */
    private function openAiResponse(array $evaluation): array
    {
        return [
            'id' => 'resp_test',
            'output' => [
                [
                    'type' => 'message',
                    'content' => [
                        [
                            'type' => 'output_text',
                            'text' => json_encode($evaluation, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validEvaluationPayload(): array
    {
        return [
            'status' => 'pending',
            'editorial_score' => 86,
            'localized' => [
                'title' => 'AIチップの部品コストでHBMの比率が上昇',
                'summary' => 'AIチップの部品コストに占めるHBMの割合が伸びています。',
                'why_it_matters' => 'AIインフラの供給網と設備投資に影響する可能性があります。',
            ],
            'scores' => [
                'preference' => 90,
                'general_importance' => 85,
                'freshness' => 80,
                'certainty' => 88,
                'scenario_fitness' => 82,
                'flow_flexibility' => 70,
            ],
            'flags' => [
                'is_duplicate_candidate' => false,
                'is_uncertain' => false,
                'is_sensitive' => false,
            ],
            'duplicate' => [
                'canonical_key' => 'ai-chip-hbm-component-costs',
                'similar_topic_ids' => [],
                'duplicate_of' => null,
                'confidence' => null,
                'reason' => null,
            ],
            'scenario_notes' => [
                'suggested_role' => 'top_story',
                'tone' => 'serious_but_accessible',
                'transition_hint' => 'AIインフラのコスト構造という流れで紹介できる',
                'avoid' => [],
            ],
            'reasons' => [
                'high technical relevance',
                'source confidence is high',
            ],
            'metadata' => [
                'schema_version' => '1.0',
            ],
        ];
    }

    private function draft(): TopicDraft
    {
        return new TopicDraft(
            id: 'upstream:213',
            sourceType: 'upstream',
            sourceName: 'Hacker News',
            title: 'AI chip memory costs',
            originalTitle: 'AI chip memory costs',
            url: 'https://example.test/article',
            discussionUrl: 'https://news.ycombinator.com/item?id=213',
            summarySeed: 'High-bandwidth memory is becoming a larger share of AI chip component costs.',
            whyItMattersSeed: 'This may affect AI infrastructure cost and supply chains.',
            tags: ['AI chips', 'HBM'],
            entities: ['Example Corp'],
            importance: 5,
            confidence: 0.95,
            contentType: 'data_analysis_article',
            limitations: null,
            publishedAt: CarbonImmutable::parse('2026-05-25T10:00:00Z'),
            fetchedAt: CarbonImmutable::parse('2026-05-25T12:00:00Z'),
            sourceRefs: [
                'provider' => 'digestpipe',
                'upstream_id' => 213,
            ],
        );
    }
}
