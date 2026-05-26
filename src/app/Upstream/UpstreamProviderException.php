<?php

namespace App\Upstream;

use RuntimeException;

/**
 * Upstream Provider からの取得・正規化失敗を表現する例外
 */
class UpstreamProviderException extends RuntimeException
{
    /**
     * HTTP Response が失敗した場合の例外を作成して返す
     */
    public static function failedHttpResponse(string $provider, int $status): self
    {
        return new self("Upstream provider [{$provider}] returned HTTP status [{$status}].");
    }

    /**
     * Provider Response が期待した形式ではない場合の例外を作成して返す
     */
    public static function invalidResponse(string $provider): self
    {
        return new self("Upstream provider [{$provider}] returned an invalid response.");
    }
}
