<?php

namespace App\Weather;

use RuntimeException;

/**
 * 天気の取得・正規化失敗を表現する例外
 */
class WeatherProviderException extends RuntimeException
{
    /**
     * HTTP Response が失敗した場合の例外を作成して返す
     *
     * @param string $provider
     * @param int $status
     *
     * @return WeatherProviderException
     */
    public static function failedHttpResponse(string $provider, int $status): self
    {
        return new self("Weather provider [{$provider}] returned HTTP status [{$status}].");
    }

    /**
     * Provider Response が期待した形式ではない場合の例外を作成して返す
     *
     * @param string $provider
     *
     * @return WeatherProviderException
     */
    public static function invalidResponse(string $provider): self
    {
        return new self("Weather provider [{$provider}] returned an invalid response.");
    }
}
