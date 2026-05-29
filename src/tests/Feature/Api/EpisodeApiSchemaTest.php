<?php

namespace Tests\Feature\Api;

use App\ApiTokens\ApiTokenService;
use App\Models\CharacterProfile;
use App\Models\Episode;
use App\Models\EpisodeTopic;
use App\Models\User;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use JsonException;
use Tests\TestCase;

/**
 * @internal
 */
class EpisodeApiSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function testEpisodeIndexResponseMatchesOpenApiSchema(): void
    {
        $this->episodeWithTopic('episode_schema_index');

        $payload = $this->jsonPayload($this->withReadToken()
            ->getJson('/api/episodes')
            ->assertOk());

        $this->assertMatchesOpenApiSchema($payload, 'EpisodeIndexResponse');
        $this->assertInternalFieldsAreHidden($payload);
    }

    public function testLatestEpisodeResponseMatchesOpenApiSchema(): void
    {
        $this->episodeWithTopic('episode_schema_latest');

        $payload = $this->jsonPayload($this->withReadToken()
            ->getJson('/api/episodes/latest')
            ->assertOk());

        $this->assertMatchesOpenApiSchema($payload, 'EpisodeDetailResponse');
        $this->assertInternalFieldsAreHidden($payload);
    }

    public function testShowEpisodeResponseMatchesOpenApiSchema(): void
    {
        $this->episodeWithTopic('episode_schema_show');

        $payload = $this->jsonPayload($this->withReadToken()
            ->getJson('/api/episodes/episode_schema_show')
            ->assertOk());

        $this->assertMatchesOpenApiSchema($payload, 'EpisodeDetailResponse');
        $this->assertInternalFieldsAreHidden($payload);
    }

    /**
     * episodes:read token を付けた test client を返す。
     */
    private function withReadToken(): self
    {
        $plainTextToken = User::factory()
            ->create(['email' => 'schema-api-user@example.test'])
            ->createToken('radiopipe-api', [ApiTokenService::ABILITY_EPISODES_READ])
            ->plainTextToken;

        return $this->withHeader('Authorization', 'Bearer ' . $plainTextToken);
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
     * schema validation 用の Episode と EpisodeTopic を作成する。
     */
    private function episodeWithTopic(string $episodeKey): Episode
    {
        $profile = CharacterProfile::factory()->create([
            'character_key' => 'schema_character',
            'name' => 'スキーマ確認キャラクター',
        ]);

        $episode = Episode::query()->create([
            'episode_key' => $episodeKey,
            'date' => '2026-05-30',
            'published_at' => '2026-05-30 07:00:00',
            'processed_at' => '2026-05-30 07:01:00',
            'character_profile_id' => $profile->id,
            'character_key' => $profile->character_key,
            'status' => Episode::STATUS_COMPLETED,
            'title' => 'OpenAPI スキーマ確認番組',
            'language' => 'ja',
            'target_duration_seconds' => 900,
            'estimated_duration_seconds' => 180,
            'scenario_json' => [
                'title' => 'OpenAPI スキーマ確認番組',
                'language' => 'ja',
                'target_duration_seconds' => 900,
                'estimated_duration_seconds' => 180,
                'character_key' => $profile->character_key,
                'script_text' => 'OpenAPI スキーマ確認用の読み上げ原稿です。',
                'sections' => [
                    [
                        'type' => 'opening',
                        'title' => 'オープニング',
                        'text' => '今日のニュースを確認します。',
                        'topic_ids' => [],
                        'estimated_duration_seconds' => 30,
                        'metadata' => ['role' => 'opening'],
                    ],
                    [
                        'type' => 'topic',
                        'title' => 'スキーマ確認トピック',
                        'text' => '次の話題です。スキーマ確認トピックです。',
                        'topic_ids' => ['upstream:schema'],
                        'estimated_duration_seconds' => 90,
                        'metadata' => ['role' => 'topic'],
                    ],
                    [
                        'type' => 'closing',
                        'title' => 'エンディング',
                        'text' => '今日のニュースはここまでです。',
                        'topic_ids' => [],
                        'estimated_duration_seconds' => 30,
                        'metadata' => ['role' => 'closing'],
                    ],
                ],
                'metadata' => ['driver' => 'fake'],
            ],
            'metadata' => ['compile_fingerprint' => str_repeat('c', 64)],
            'errors' => null,
        ]);

        EpisodeTopic::query()->create([
            'episode_id' => $episode->id,
            'topic_id' => 'upstream:schema',
            'source_name' => 'Schema News',
            'source_type' => 'rss',
            'title' => 'スキーマ確認トピック',
            'url' => 'https://example.test/schema-topic',
            'screening_status' => 'passed',
            'editorial_status' => 'pending',
            'scenario_selection_status' => 'used_in_scenario',
            'sort_order' => 1,
            'topic_draft_json' => [
                'title' => 'スキーマ確認トピック',
                'summary_seed' => 'スキーマ確認用の概要です。',
                'why_it_matters_seed' => '下流 client の型安全性に関わります。',
                'source_name' => 'Schema News',
                'url' => 'https://example.test/schema-topic',
                'discussion_url' => 'https://example.test/discussion',
                'prompt' => 'internal prompt placeholder',
            ],
            'screening_json' => ['status' => 'passed'],
            'editorial_json' => [
                'localized' => [
                    'title' => 'スキーマ確認トピック',
                    'summary' => 'スキーマ確認用の概要です。',
                    'why_it_matters' => '下流 client の型安全性に関わります。',
                ],
            ],
            'scenario_selection_json' => ['status' => 'used_in_scenario'],
            'metadata' => ['raw_model_response' => 'internal response placeholder'],
        ]);

        return $episode;
    }

    /**
     * API payload が OpenAPI components schema に一致することを確認する。
     *
     * @param array<string, mixed> $payload
     */
    private function assertMatchesOpenApiSchema(array $payload, string $schemaName): void
    {
        $schema = $this->schema($schemaName);

        $this->assertValueMatchesSchema($payload, $schema, '$');
    }

    /**
     * API payload に内部 snapshot や機密系フィールド名が含まれないことを確認する。
     *
     * @param array<string, mixed> $payload
     *
     * @throws JsonException
     */
    private function assertInternalFieldsAreHidden(array $payload): void
    {
        $json = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        foreach ([
            'topic_draft_json',
            'screening_json',
            'editorial_json',
            'scenario_selection_json',
            'candidate_fingerprint',
            'compile_fingerprint',
            'raw_model_response',
            'prompt',
            'api_key',
        ] as $hiddenField) {
            self::assertStringNotContainsString($hiddenField, $json);
        }
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
        $path = base_path('../docs/openapi.yaml');
        $contents = file_get_contents($path);

        self::assertIsString($contents, 'docs/openapi.yaml could not be read.');

        $document = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);

        self::assertIsArray($document);
        self::assertSame('3.1.0', data_get($document, 'openapi'));
        self::assertIsArray(data_get($document, 'paths./api/episodes'));
        self::assertIsArray(data_get($document, 'paths./api/episodes/latest'));
        self::assertIsArray(data_get($document, 'paths./api/episodes/{episode_key}'));

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

        if ($actualType === 'array') {
            self::assertIsArray($value);
            self::assertTrue(array_is_list($value), "{$path} must be a JSON array.");
            $this->assertArrayMatchesSchema($value, $schema, $path);

            return;
        }

        if ($actualType === 'string') {
            self::assertIsString($value);
            $this->assertStringMatchesSchema($value, $schema, $path);

            return;
        }

        if (($actualType === 'integer' || $actualType === 'number') && isset($schema['minimum'])) {
            self::assertIsInt($value);
            self::assertGreaterThanOrEqual($schema['minimum'], $value, "{$path} is below minimum.");
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
     * array 値が schema に一致することを確認する。
     *
     * @param list<mixed> $value
     * @param array<string, mixed> $schema
     */
    private function assertArrayMatchesSchema(array $value, array $schema, string $path): void
    {
        if (! isset($schema['items'])) {
            return;
        }

        self::assertIsArray($schema['items']);

        foreach ($value as $index => $item) {
            /** @var array<string, mixed> $itemSchema */
            $itemSchema = $schema['items'];
            $this->assertValueMatchesSchema($item, $itemSchema, "{$path}[{$index}]");
        }
    }

    /**
     * string 値が schema format に一致することを確認する。
     *
     * @param array<string, mixed> $schema
     */
    private function assertStringMatchesSchema(string $value, array $schema, string $path): void
    {
        $format = $schema['format'] ?? null;

        if ($format === 'date-time') {
            self::assertInstanceOf(DateTimeImmutable::class, new DateTimeImmutable($value), "{$path} is not a valid date-time.");

            return;
        }

        if ($format === 'uri') {
            self::assertNotFalse(filter_var($value, FILTER_VALIDATE_URL), "{$path} is not a valid URI.");
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
