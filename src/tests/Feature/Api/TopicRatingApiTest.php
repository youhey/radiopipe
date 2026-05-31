<?php

namespace Tests\Feature\Api;

use App\ApiTokens\ApiTokenService;
use App\Models\CandidateTopic;
use App\Models\Episode;
use App\Models\EpisodeTopic;
use App\Models\User;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use JsonException;
use Tests\TestCase;

/**
 * @internal
 */
class TopicRatingApiTest extends TestCase
{
    use RefreshDatabase;

    public function testTokenWithTopicsRateAbilityCanSetBadRating(): void
    {
        $this->rateableCandidateTopic();
        $this->fakeUpstreamSetResponse(-1);

        $this->withRateToken()
            ->putJson($this->ratingPath('upstream:236'), ['rating' => -1])
            ->assertOk()
            ->assertJsonPath('topic_rating.topic_id', 'upstream:236')
            ->assertJsonPath('topic_rating.upstream.provider', 'digestpipe')
            ->assertJsonPath('topic_rating.upstream.id', 236)
            ->assertJsonPath('topic_rating.rating', -1)
            ->assertJsonPath('topic_rating.rated_at', '2026-05-31T10:15:00+09:00')
            ->assertJsonMissingPath('topic_rating.manual_rating')
            ->assertJsonMissingPath('topic_rating.manual_rated_at');

        Http::assertSent(function (Request $request): bool {
            return $request->method() === 'PUT'
                && $request->url() === 'https://digestpipe.test/api/articles/236/rating'
                && $request->hasHeader('Authorization', 'Bearer upstream-token')
                && ($request->data()['rating'] ?? null) === -1;
        });
    }

    public function testTokenWithTopicsRateAbilityCanSetGoodRating(): void
    {
        $this->rateableCandidateTopic();
        $this->fakeUpstreamSetResponse(1);

        $this->withRateToken()
            ->putJson($this->ratingPath('upstream:236'), ['rating' => 1])
            ->assertOk()
            ->assertJsonPath('topic_rating.rating', 1);
    }

    public function testTokenWithTopicsRateAbilityCanSetFiveStarRating(): void
    {
        $this->rateableCandidateTopic();
        $this->fakeUpstreamSetResponse(5);

        $this->withRateToken()
            ->putJson($this->ratingPath('upstream:236'), ['rating' => 5])
            ->assertOk()
            ->assertJsonPath('topic_rating.rating', 5);
    }

    public function testDeleteClearsTopicRating(): void
    {
        $this->rateableCandidateTopic();
        $this->fakeUpstreamClearResponse();

        $this->withRateToken()
            ->deleteJson($this->ratingPath('upstream:236'))
            ->assertOk()
            ->assertJsonPath('topic_rating.topic_id', 'upstream:236')
            ->assertJsonPath('topic_rating.upstream.provider', 'digestpipe')
            ->assertJsonPath('topic_rating.upstream.id', 236)
            ->assertJsonPath('topic_rating.rating', null)
            ->assertJsonPath('topic_rating.rated_at', null);

        Http::assertSent(function (Request $request): bool {
            return $request->method() === 'DELETE'
                && $request->url() === 'https://digestpipe.test/api/articles/236/rating'
                && $request->hasHeader('Authorization', 'Bearer upstream-token');
        });
    }

    public function testEpisodeTopicCanBeUsedWhenCandidateTopicIsMissing(): void
    {
        $this->rateableEpisodeTopic();
        $this->fakeUpstreamSetResponse(1, articleId: 999);

        $this->withRateToken()
            ->putJson($this->ratingPath('upstream:999'), ['rating' => 1])
            ->assertOk()
            ->assertJsonPath('topic_rating.topic_id', 'upstream:999')
            ->assertJsonPath('topic_rating.upstream.id', 999);
    }

    public function testInvalidRatingValuesReturnValidationError(): void
    {
        foreach ([0, -2, 6, null, 'bad'] as $rating) {
            $payload = $rating === null ? [] : ['rating' => $rating];

            $this->withRateToken()
                ->putJson($this->ratingPath('upstream:236'), $payload)
                ->assertUnprocessable();
        }
    }

    public function testUnauthenticatedRequestsAreRejected(): void
    {
        $this->putJson($this->ratingPath('upstream:236'), ['rating' => 1])
            ->assertUnauthorized();

        $this->deleteJson($this->ratingPath('upstream:236'))
            ->assertUnauthorized();
    }

    public function testTokenWithoutTopicsRateAbilityIsRejected(): void
    {
        $plainTextToken = User::factory()
            ->create(['email' => 'read-only@example.test'])
            ->createToken('radiopipe-api', [ApiTokenService::ABILITY_EPISODES_READ])
            ->plainTextToken;

        $this->withHeader('Authorization', 'Bearer ' . $plainTextToken)
            ->putJson($this->ratingPath('upstream:236'), ['rating' => 1])
            ->assertForbidden();

        $this->withHeader('Authorization', 'Bearer ' . $plainTextToken)
            ->deleteJson($this->ratingPath('upstream:236'))
            ->assertForbidden();
    }

    public function testUnknownTopicReturnsNotFound(): void
    {
        $this->withRateToken()
            ->putJson($this->ratingPath('upstream:missing'), ['rating' => 1])
            ->assertNotFound();
    }

    public function testTopicWithoutDigestpipeMappingReturnsNotFound(): void
    {
        $this->rateableCandidateTopic([
            'topic_id' => 'local:1',
            'upstream_provider' => 'fake',
            'upstream_id' => '1',
        ]);

        $this->withRateToken()
            ->putJson($this->ratingPath('local:1'), ['rating' => 1])
            ->assertNotFound();
    }

    public function testUpstreamErrorIsHandledSafely(): void
    {
        $this->rateableCandidateTopic();
        $this->configureUpstream();
        Http::fake([
            'https://digestpipe.test/api/articles/236/rating' => Http::response([
                'message' => 'upstream internal details',
                'manual_rating' => 1,
                'api_key' => 'secret',
            ], 500),
        ]);

        $this->withRateToken()
            ->putJson($this->ratingPath('upstream:236'), ['rating' => 1])
            ->assertStatus(502)
            ->assertJsonPath('message', 'Upstream rating API request failed.')
            ->assertJsonMissingPath('manual_rating')
            ->assertJsonMissingPath('api_key');
    }

    public function testTopicRatingResponsesMatchOpenApiSchema(): void
    {
        $this->rateableCandidateTopic();
        $this->fakeUpstreamSetResponse(1);

        $putPayload = $this->jsonPayload($this->withRateToken()
            ->putJson($this->ratingPath('upstream:236'), ['rating' => 1])
            ->assertOk());

        $this->assertMatchesOpenApiSchema($putPayload, 'TopicRatingResponse');

        $this->fakeUpstreamClearResponse();

        $deletePayload = $this->jsonPayload($this->withRateToken()
            ->deleteJson($this->ratingPath('upstream:236'))
            ->assertOk());

        $this->assertMatchesOpenApiSchema($deletePayload, 'TopicRatingResponse');
    }

    public function testOpenApiDefinesTopicRatingRequestSchema(): void
    {
        self::assertIsArray(data_get($this->openApi(), 'paths./api/topics/{id}/rating.put'));
        self::assertIsArray(data_get($this->openApi(), 'paths./api/topics/{id}/rating.delete'));
        self::assertIsArray(data_get($this->openApi(), 'components.schemas.TopicRatingRequest'));
    }

    /**
     * topics:rate token を付けた test client を返す。
     */
    private function withRateToken(): self
    {
        $plainTextToken = User::factory()
            ->create()
            ->createToken('radiopipe-api', [ApiTokenService::ABILITY_TOPICS_RATE])
            ->plainTextToken;

        return $this->withHeader('Authorization', 'Bearer ' . $plainTextToken);
    }

    /**
     * topic rating endpoint の path を返す。
     */
    private function ratingPath(string $topicId): string
    {
        return '/api/topics/' . rawurlencode($topicId) . '/rating';
    }

    /**
     * digestpipe rating forwarding 設定を test 用に入れる。
     */
    private function configureUpstream(): void
    {
        config([
            'radiopipe.upstream.url' => 'https://digestpipe.test',
            'radiopipe.upstream.key' => 'upstream-token',
            'radiopipe.upstream.request_timeout' => 30,
            'radiopipe.upstream.max_retries' => 0,
        ]);
    }

    /**
     * upstream PUT response を fake する。
     */
    private function fakeUpstreamSetResponse(int $rating, int $articleId = 236): void
    {
        $this->configureUpstream();
        Http::fake([
            "https://digestpipe.test/api/articles/{$articleId}/rating" => Http::response([
                'article_rating' => [
                    'article_id' => $articleId,
                    'rating' => $rating,
                    'rated_at' => '2026-05-31T10:15:00+09:00',
                    'manual_rating' => $rating,
                ],
            ], 200),
        ]);
    }

    /**
     * upstream DELETE response を fake する。
     */
    private function fakeUpstreamClearResponse(): void
    {
        $this->configureUpstream();
        Http::fake([
            'https://digestpipe.test/api/articles/236/rating' => Http::response([
                'article_rating' => [
                    'article_id' => 236,
                    'rating' => null,
                    'rated_at' => null,
                    'manual_rating' => null,
                    'manual_rated_at' => null,
                ],
            ], 200),
        ]);
    }

    /**
     * rating 可能な CandidateTopic を作成する。
     *
     * @param array<string, mixed> $overrides
     */
    private function rateableCandidateTopic(array $overrides = []): CandidateTopic
    {
        return CandidateTopic::query()->create(array_merge([
            'topic_id' => 'upstream:236',
            'source_type' => 'article',
            'source_name' => 'Digestpipe',
            'upstream_provider' => 'digestpipe',
            'upstream_id' => '236',
            'topic_draft_json' => ['title' => 'Rating target'],
            'screening_json' => ['status' => 'passed'],
            'editorial_json' => ['status' => 'pending'],
            'screening_status' => 'passed',
            'editorial_status' => 'pending',
            'candidate_fingerprint' => str_repeat('a', 64),
            'processed_at' => '2026-05-31 10:00:00',
            'metadata' => [],
        ], $overrides));
    }

    /**
     * rating 可能な EpisodeTopic を作成する。
     */
    private function rateableEpisodeTopic(): EpisodeTopic
    {
        $episode = Episode::query()->create([
            'episode_key' => 'episode_rating_topic',
            'date' => '2026-05-31',
            'processed_at' => '2026-05-31 10:00:00',
            'status' => Episode::STATUS_COMPLETED,
            'language' => 'ja',
            'scenario_json' => [],
            'metadata' => [],
        ]);

        return EpisodeTopic::query()->create([
            'episode_id' => $episode->id,
            'topic_id' => 'upstream:999',
            'upstream_provider' => 'digestpipe',
            'upstream_id' => '999',
            'sort_order' => 1,
            'topic_draft_json' => [],
            'screening_json' => [],
            'editorial_json' => [],
            'scenario_selection_json' => [],
            'metadata' => [],
        ]);
    }

    /**
     * JSON response payload を配列として返す。
     *
     * @return array<string, mixed>
     */
    private function jsonPayload(mixed $response): array
    {
        self::assertInstanceOf(TestResponse::class, $response);

        $payload = $response->json();

        self::assertIsArray($payload);

        /** @var array<string, mixed> $payload */
        return $payload;
    }

    /**
     * API payload が OpenAPI components schema に一致することを確認する。
     *
     * @param array<string, mixed> $payload
     */
    private function assertMatchesOpenApiSchema(array $payload, string $schemaName): void
    {
        $this->assertValueMatchesSchema($payload, $this->schema($schemaName), '$');
    }

    /**
     * OpenAPI component schema を返す。
     *
     * @return array<string, mixed>
     */
    private function schema(string $schemaName): array
    {
        $schema = data_get($this->openApi(), "components.schemas.{$schemaName}");

        self::assertIsArray($schema, "OpenAPI schema [{$schemaName}] is missing.");

        /** @var array<string, mixed> $schema */
        return $schema;
    }

    /**
     * OpenAPI document を読み込む。
     *
     * @return array<string, mixed>
     *
     * @throws JsonException
     */
    private function openApi(): array
    {
        $contents = file_get_contents(base_path('../docs/openapi.yaml'));

        self::assertIsString($contents, 'docs/openapi.yaml could not be read.');

        $document = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);

        self::assertIsArray($document);
        self::assertSame('3.1.0', data_get($document, 'openapi'));

        /** @var array<string, mixed> $document */
        return $document;
    }

    /**
     * 値が schema に一致することを確認する。
     *
     * @param array<string, mixed> $schema
     */
    private function assertValueMatchesSchema(mixed $value, array $schema, string $path): void
    {
        if (isset($schema['$ref'])) {
            self::assertIsString($schema['$ref']);
            $this->assertValueMatchesSchema($value, $this->schemaFromRef($schema['$ref']), $path);

            return;
        }

        if (array_key_exists('enum', $schema)) {
            $enum = $schema['enum'];
            self::assertIsArray($enum);
            self::assertContains($value, $enum, "{$path} is not an allowed enum value.");
        }

        $types = $this->schemaTypes($schema);
        $actualType = $this->jsonType($value);

        self::assertContains($actualType, $types, "{$path} expected type [" . implode('|', $types) . "], got [{$actualType}].");

        if ($value === null) {
            return;
        }

        if ($actualType === 'object') {
            self::assertIsArray($value);
            /** @var array<int|string, mixed> $value */
            $this->assertObjectMatchesSchema($value, $schema, $path);

            return;
        }

        if ($actualType === 'string' && ($schema['format'] ?? null) === 'date-time') {
            self::assertIsString($value);
            self::assertInstanceOf(DateTimeImmutable::class, new DateTimeImmutable($value), "{$path} is not a valid date-time.");
        }
    }

    /**
     * object 値が schema に一致することを確認する。
     *
     * @param array<array-key, mixed> $value
     * @param array<string, mixed> $schema
     */
    private function assertObjectMatchesSchema(array $value, array $schema, string $path): void
    {
        $required = $schema['required'] ?? [];
        self::assertIsArray($required);

        foreach ($required as $property) {
            self::assertIsString($property);
            self::assertArrayHasKey($property, $value, "{$path}.{$property} is required.");
        }

        $properties = $schema['properties'] ?? [];
        self::assertIsArray($properties);

        if (($schema['additionalProperties'] ?? true) === false) {
            $allowed = array_keys($properties);

            foreach (array_keys($value) as $property) {
                self::assertContains($property, $allowed, "{$path}.{$property} is not defined in schema.");
            }
        }

        foreach ($properties as $property => $propertySchema) {
            self::assertIsString($property);
            self::assertIsArray($propertySchema);

            if (array_key_exists($property, $value)) {
                /** @var array<string, mixed> $propertySchema */
                $this->assertValueMatchesSchema($value[$property], $propertySchema, "{$path}.{$property}");
            }
        }
    }

    /**
     * OpenAPI $ref から schema を返す。
     *
     * @return array<string, mixed>
     */
    private function schemaFromRef(string $ref): array
    {
        self::assertStringStartsWith('#/components/schemas/', $ref);

        return $this->schema(substr($ref, strlen('#/components/schemas/')));
    }

    /**
     * schema type 一覧を返す。
     *
     * @param array<string, mixed> $schema
     *
     * @return list<string>
     */
    private function schemaTypes(array $schema): array
    {
        $type = $schema['type'] ?? 'object';

        if (is_string($type)) {
            return [$type];
        }

        self::assertIsArray($type);

        $types = [];

        foreach ($type as $value) {
            self::assertIsString($value);
            $types[] = $value;
        }

        return $types;
    }

    /**
     * json_decode 後の PHP 値から JSON type を推定する。
     */
    private function jsonType(mixed $value): string
    {
        if ($value === null) {
            return 'null';
        }

        if (is_string($value)) {
            return 'string';
        }

        if (is_bool($value)) {
            return 'boolean';
        }

        if (is_int($value)) {
            return 'integer';
        }

        if (is_float($value)) {
            return 'number';
        }

        if (is_array($value)) {
            return array_is_list($value) ? 'array' : 'object';
        }

        self::fail('Unsupported JSON value type.');
    }
}
