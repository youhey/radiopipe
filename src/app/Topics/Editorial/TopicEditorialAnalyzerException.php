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
     */
    public static function failedHttpResponse(string $driver, int $status): self
    {
        return new self("Topic editorial analyzer [{$driver}] returned HTTP status [{$status}].");
    }

    /**
     * analyzer response が期待した形式ではない場合の例外を作成します。
     */
    public static function invalidResponse(string $driver): self
    {
        return new self("Topic editorial analyzer [{$driver}] returned an invalid response.");
    }
}
