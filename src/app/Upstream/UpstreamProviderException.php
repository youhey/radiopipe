<?php

namespace App\Upstream;

use RuntimeException;

/**
 * upstream provider の取得・正規化失敗を表します。
 */
class UpstreamProviderException extends RuntimeException
{
    /**
     * HTTP response が失敗した場合の例外を作成します。
     */
    public static function failedHttpResponse(string $provider, int $status): self
    {
        return new self("Upstream provider [{$provider}] returned HTTP status [{$status}].");
    }

    /**
     * provider response が期待した形式ではない場合の例外を作成します。
     */
    public static function invalidResponse(string $provider): self
    {
        return new self("Upstream provider [{$provider}] returned an invalid response.");
    }
}
