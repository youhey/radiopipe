<?php

namespace App\Scenarios;

use RuntimeException;

/**
 * Scenario generator の取得・正規化失敗を表現する例外。
 */
class ScenarioGeneratorException extends RuntimeException
{
    /**
     * HTTP Response が失敗した場合の例外を作成して返す。
     *
     * @param array{message?: string, type?: string, code?: string} $error
     */
    public static function failedHttpResponse(string $driver, int $status, array $error = []): self
    {
        $details = [];

        foreach (['message', 'type', 'code'] as $key) {
            $value = $error[$key] ?? null;

            if ($value === null || trim($value) === '') {
                continue;
            }

            $details[] = 'error.' . $key . ' [' . self::normalizeDetail($value) . ']';
        }

        $suffix = $details === [] ? '' : ' ' . implode(' ', $details);

        return new self("Scenario generator [{$driver}] returned HTTP status [{$status}].{$suffix}");
    }

    /**
     * Generator Response が期待した形式ではない場合の例外を作成して返す。
     */
    public static function invalidResponse(string $driver, string $reason = 'invalid response'): self
    {
        return new self("Scenario generator [{$driver}] returned an invalid response: {$reason}.");
    }

    /**
     * OpenAI のエラー詳細をログ向けに一行へ整えて返す。
     */
    private static function normalizeDetail(string $value): string
    {
        return trim((string) preg_replace('/\s+/', ' ', $value));
    }
}
