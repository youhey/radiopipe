<?php

namespace App\Topics\Editorial;

use RuntimeException;

/**
 * Topic Editorial Analyzer の取得・正規化失敗を表します。
 */
class TopicEditorialAnalyzerException extends RuntimeException
{
    /**
     * HTTP response が失敗した場合の例外を作成します。
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

        return new self("Topic editorial analyzer [{$driver}] returned HTTP status [{$status}].{$suffix}");
    }

    /**
     * analyzer response が期待した形式ではない場合の例外を作成します。
     */
    public static function invalidResponse(string $driver): self
    {
        return new self("Topic editorial analyzer [{$driver}] returned an invalid response.");
    }

    /**
     * OpenAI のエラー詳細をログ向けに一行へ整えます。
     */
    private static function normalizeDetail(string $value): string
    {
        return trim((string) preg_replace('/\s+/', ' ', $value));
    }
}
