<?php

namespace Tests\Unit\Scenarios;

use App\Models\CharacterProfile;
use App\Scenarios\FakeScenarioGenerator;
use App\Scenarios\OpenAiScenarioGenerator;
use App\Scenarios\ScenarioGenerationInput;
use App\Scenarios\ScenarioGenerationResult;
use App\Scenarios\ScenarioGenerator;
use App\Scenarios\ScenarioGeneratorException;
use App\Topics\Editorial\TopicDuplicateAssessment;
use App\Topics\Editorial\TopicEditorialEvaluation;
use App\Topics\Editorial\TopicEditorialFlags;
use App\Topics\Editorial\TopicEditorialScores;
use App\Topics\Editorial\TopicEditorialStatus;
use App\Topics\Editorial\TopicLocalizedText;
use App\Topics\Editorial\TopicScenarioNotes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * @internal
 */
class OpenAiScenarioGeneratorTest extends TestCase
{
    use RefreshDatabase;

    public function testItMapsValidStructuredOpenAiResponseToScenarioResult(): void
    {
        $this->profile();
        Http::fake([
            'https://api.openai.com/v1/responses' => Http::response($this->openAiResponse($this->validScenarioPayload()), 200),
        ]);

        $result = $this->generator()->generate($this->input());

        self::assertInstanceOf(ScenarioGenerationResult::class, $result);
        self::assertSame('openai', $result->metadata['generator']);
        self::assertSame('gpt-test', $result->metadata['model']);
        self::assertSame(1, $result->metadata['selected_topic_count']);
        self::assertSame('今日のギークニュース', $result->scenario->title);
        self::assertSame('openai', $result->scenario->metadata['driver']);
        self::assertSame('topic-a', $result->scenario->sections[1]->topicIds[0]);
        self::assertStringContainsString('高い話題', $result->scenario->scriptText);
        self::assertCount(2, $result->topicSelections);
    }

    public function testItIncludesCharacterInstructionsAndCompactTopicInput(): void
    {
        $this->profile();
        Http::fake([
            'https://api.openai.com/v1/responses' => Http::response($this->openAiResponse($this->validScenarioPayload()), 200),
        ]);

        $this->generator()->generate($this->input());

        Http::assertSent(function (Request $request): bool {
            $payload = $request->data();
            $instructions = $payload['instructions'] ?? null;
            $input = $payload['input'] ?? null;
            $firstInput = is_array($input) ? ($input[0] ?? null) : null;
            $content = is_array($firstInput) ? ($firstInput['content'] ?? null) : null;
            $firstContent = is_array($content) ? ($content[0] ?? null) : null;
            $text = is_array($firstContent) ? ($firstContent['text'] ?? null) : null;

            if (! is_string($instructions) || ! is_string($text)) {
                return false;
            }

            $scenarioInput = json_decode($text, true);

            if (! is_array($scenarioInput)) {
                return false;
            }

            $selectedTopics = $scenarioInput['selected_topics'] ?? null;
            $firstTopic = is_array($selectedTopics) ? ($selectedTopics[0] ?? null) : null;

            return $request->url() === 'https://api.openai.com/v1/responses'
                && $request->hasHeader('Authorization', 'Bearer test-openai-key')
                && ($payload['model'] ?? null) === 'gpt-test'
                && str_contains($instructions, 'Use only the provided topic facts')
                && str_contains($instructions, 'ダミーキャラクター')
                && str_contains($instructions, 'banned_phrases')
                && ! str_contains($instructions, 'test-openai-key')
                && is_array($firstTopic)
                && ($firstTopic['topic_id'] ?? null) === 'topic-a'
                && ($firstTopic['source_name'] ?? null) === 'Digestpipe'
                && ! array_key_exists('raw_article_body', $firstTopic)
                && ! str_contains($text, 'RAW BODY MUST NOT BE SENT');
        });
    }

    public function testItSendsEmptySchemaPropertiesAsObject(): void
    {
        $this->profile();
        Http::fake([
            'https://api.openai.com/v1/responses' => Http::response($this->openAiResponse($this->validScenarioPayload()), 200),
        ]);

        $this->generator()->generate($this->input());

        Http::assertSent(function (Request $request): bool {
            $payload = $request->data();
            $text = is_array($payload['text'] ?? null) ? $payload['text'] : null;
            $format = is_array($text) && is_array($text['format'] ?? null) ? $text['format'] : null;
            $schema = is_array($format) ? ($format['schema'] ?? null) : null;

            if (! is_array($schema)) {
                return false;
            }

            $encoded = json_encode($schema, JSON_THROW_ON_ERROR);

            return str_contains($encoded, '"metadata":{"type":"object","additionalProperties":false,"required":[],"properties":{}}');
        });
    }

    public function testItRejectsInvalidJson(): void
    {
        $this->profile();
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

        $this->expectException(ScenarioGeneratorException::class);
        $this->expectExceptionMessage('invalid JSON');

        $this->generator()->generate($this->input());
    }

    public function testItRejectsMissingRequiredFields(): void
    {
        $this->profile();
        $payload = $this->validScenarioPayload();
        unset($payload['script_text']);
        Http::fake([
            'https://api.openai.com/v1/responses' => Http::response($this->openAiResponse($payload), 200),
        ]);

        $this->expectException(ScenarioGeneratorException::class);
        $this->expectExceptionMessage('script_text');

        $this->generator()->generate($this->input());
    }

    public function testItRejectsInvalidDurationValues(): void
    {
        $this->profile();
        $payload = $this->validScenarioPayload();
        $payload['estimated_duration_seconds'] = -1;
        Http::fake([
            'https://api.openai.com/v1/responses' => Http::response($this->openAiResponse($payload), 200),
        ]);

        $this->expectException(ScenarioGeneratorException::class);
        $this->expectExceptionMessage('estimated_duration_seconds');

        $this->generator()->generate($this->input());
    }

    public function testItRejectsUnknownTopicReferences(): void
    {
        $this->profile();
        $payload = $this->validScenarioPayload();
        $sections = $payload['sections'];
        self::assertIsArray($sections);
        $topicSection = $sections[1];
        self::assertIsArray($topicSection);
        $topicSection['topic_ids'] = ['unknown-topic'];
        $sections[1] = $topicSection;
        $payload['sections'] = $sections;
        Http::fake([
            'https://api.openai.com/v1/responses' => Http::response($this->openAiResponse($payload), 200),
        ]);

        $this->expectException(ScenarioGeneratorException::class);
        $this->expectExceptionMessage('unknown topic id [unknown-topic]');

        $this->generator()->generate($this->input());
    }

    public function testItHandlesMissingApiKeyWhenOpenAiGeneratorIsConfigured(): void
    {
        config([
            'radiopipe.scenario.generator' => 'openai',
            'radiopipe.openai.api_key' => null,
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('OPENAI_API_KEY must be configured');

        $this->app->make(ScenarioGenerator::class);
    }

    public function testServiceBindingResolvesFakeByDefault(): void
    {
        config(['radiopipe.scenario.generator' => 'fake']);

        self::assertInstanceOf(
            FakeScenarioGenerator::class,
            $this->app->make(ScenarioGenerator::class),
        );
    }

    public function testServiceBindingResolvesOpenAiWhenConfigured(): void
    {
        config([
            'radiopipe.scenario.generator' => 'openai',
            'radiopipe.scenario.model' => 'gpt-test',
            'radiopipe.openai.api_key' => 'test-openai-key',
        ]);

        self::assertInstanceOf(
            OpenAiScenarioGenerator::class,
            $this->app->make(ScenarioGenerator::class),
        );
    }

    public function testInvalidGeneratorDriverFailsClearly(): void
    {
        config(['radiopipe.scenario.generator' => 'invalid']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported radiopipe scenario generator [invalid].');

        $this->app->make(ScenarioGenerator::class);
    }

    public function testHttpFailureFailsClearly(): void
    {
        $this->profile();
        Http::fake([
            'https://api.openai.com/v1/responses' => Http::response([
                'error' => [
                    'message' => 'Invalid schema for response format.',
                    'type' => 'invalid_request_error',
                    'code' => 'invalid_json_schema',
                ],
            ], 400),
        ]);

        $this->expectException(ScenarioGeneratorException::class);
        $this->expectExceptionMessage('HTTP status [400]');
        $this->expectExceptionMessage('error.message [Invalid schema for response format.]');
        $this->expectExceptionMessage('error.type [invalid_request_error]');
        $this->expectExceptionMessage('error.code [invalid_json_schema]');

        $this->generator()->generate($this->input());
    }

    public function testItDoesNotRequireRealNetworkAccess(): void
    {
        $this->profile();
        Http::fake([
            'https://api.openai.com/v1/responses' => Http::response($this->openAiResponse($this->validScenarioPayload()), 200),
        ]);

        $this->generator()->generate($this->input());

        Http::assertSentCount(1);
    }

    private function generator(): OpenAiScenarioGenerator
    {
        return new OpenAiScenarioGenerator(
            apiKey: 'test-openai-key',
            model: 'gpt-test',
            timeout: 60,
            maxRetries: 0,
            maxTopics: 1,
        );
    }

    private function input(): ScenarioGenerationInput
    {
        return new ScenarioGenerationInput(
            characterKey: 'dummy_radio',
            targetDurationSeconds: 900,
            title: '今日のギークニュース',
            language: 'ja',
            editorialEvaluations: [
                $this->evaluation('topic-a', '高い話題', 90),
                $this->evaluation('topic-b', '低い話題', 70),
                $this->evaluation('topic-skipped', '除外話題', 100, TopicEditorialStatus::SkippedSensitive),
            ],
        );
    }

    private function profile(): CharacterProfile
    {
        return CharacterProfile::factory()->create([
            'character_key' => 'dummy_radio',
            'name' => 'ダミーキャラクター',
        ]);
    }

    /**
     * @param array<string, mixed> $scenario
     *
     * @return array<string, mixed>
     */
    private function openAiResponse(array $scenario): array
    {
        return [
            'id' => 'resp_test',
            'output' => [
                [
                    'type' => 'message',
                    'content' => [
                        [
                            'type' => 'output_text',
                            'text' => json_encode($scenario, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validScenarioPayload(): array
    {
        return [
            'title' => '今日のギークニュース',
            'language' => 'ja',
            'target_duration_seconds' => 900,
            'estimated_duration_seconds' => 180,
            'character_key' => 'dummy_radio',
            'script_text' => 'さてさて、今日のニュースです。高い話題を紹介します。今日はここまでです。',
            'sections' => [
                [
                    'type' => 'opening',
                    'title' => 'オープニング',
                    'text' => 'さてさて、今日のニュースです。',
                    'topic_ids' => [],
                    'estimated_duration_seconds' => 30,
                    'metadata' => [],
                ],
                [
                    'type' => 'topic',
                    'title' => '高い話題',
                    'text' => '高い話題を紹介します。',
                    'topic_ids' => ['topic-a'],
                    'estimated_duration_seconds' => 120,
                    'metadata' => [],
                ],
                [
                    'type' => 'closing',
                    'title' => 'エンディング',
                    'text' => '今日はここまでです。',
                    'topic_ids' => [],
                    'estimated_duration_seconds' => 30,
                    'metadata' => [],
                ],
            ],
            'metadata' => [
                'schema_version' => '1.0',
                'driver' => 'openai',
            ],
        ];
    }

    private function evaluation(
        string $topicId,
        string $title,
        int $score,
        TopicEditorialStatus $status = TopicEditorialStatus::Pending,
    ): TopicEditorialEvaluation {
        return new TopicEditorialEvaluation(
            status: $status,
            editorialScore: $score,
            localized: new TopicLocalizedText(
                title: $title,
                summary: "{$title} の要約です。",
                whyItMatters: "{$title} の背景です。",
            ),
            scores: new TopicEditorialScores(80, 80, 80, 80, 80, 80),
            flags: new TopicEditorialFlags(false, false, false),
            duplicate: new TopicDuplicateAssessment(null, [], null, null, null),
            scenarioNotes: new TopicScenarioNotes('main_story', 'neutral', '短くつなぐ', []),
            metadata: [
                'topic_id' => $topicId,
                'source_name' => 'Digestpipe',
                'url' => 'https://example.test/article',
                'discussion_url' => 'https://news.ycombinator.com/item?id=1',
                'limitations' => '現時点では限定的な情報です。',
                'raw_article_body' => 'RAW BODY MUST NOT BE SENT',
            ],
        );
    }
}
