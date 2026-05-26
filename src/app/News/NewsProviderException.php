<?php

namespace App\News;

use RuntimeException;

/**
 * News Provider の取得・正規化失敗を表現する例外
 */
class NewsProviderException extends RuntimeException
{
    /**
     * HTTP Response が失敗した場合の例外を生成して返す
     */
    public static function failedHttpResponse(string $provider, int $status): self
    {
        return new self("News provider [{$provider}] returned HTTP status [{$status}].");
    }

    /**
     * Provider Response が期待する形式ではない場合の例外を生成して返す
     */
    public static function invalidResponse(string $provider): self
    {
        return new self("News provider [{$provider}] returned an invalid response.");
    }
}
