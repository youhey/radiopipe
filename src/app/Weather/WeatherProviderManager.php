<?php

namespace App\Weather;

use InvalidArgumentException;

/**
 * 設定に基づいて Weather Provider と既定 Query を解決
 */
class WeatherProviderManager
{
    /**
     * provider 名から weather provider を解決
     */
    public function driver(?string $driver = null): WeatherProvider
    {
        $resolvedDriver = $driver ?? $this->stringConfig('radiopipe.weather.provider', 'fake');

        return match ($resolvedDriver) {
            'fake' => new FakeWeatherProvider(),
            'open_meteo' => new OpenMeteoWeatherProvider(
                $this->stringConfig('radiopipe.open_meteo.base_url', 'https://api.open-meteo.com'),
                $this->intConfig('radiopipe.weather.request_timeout', 10),
                $this->intConfig('radiopipe.weather.max_retries', 2),
            ),
            default => throw new InvalidArgumentException("Unsupported radiopipe weather provider [{$resolvedDriver}]."),
        };
    }

    /**
     * 設定された既定地点の query を作成して返す
     */
    public function defaultQuery(): WeatherQuery
    {
        $latitude = config('radiopipe.weather.default.latitude');
        $longitude = config('radiopipe.weather.default.longitude');

        if ((! is_int($latitude) && ! is_float($latitude)) || (! is_int($longitude) && ! is_float($longitude))) {
            throw new InvalidArgumentException('Default weather latitude and longitude must be configured.');
        }

        return new WeatherQuery(
            latitude: (float) $latitude,
            longitude: (float) $longitude,
            locationName: $this->nullableStringConfig('radiopipe.weather.default.location_name'),
            timezone: $this->nullableStringConfig('radiopipe.weather.default.timezone'),
        );
    }

    /**
     * 設定済み provider で既定地点の天気を取得して返す
     *
     * @param WeatherQuery|null $query
     *
     * @return WeatherReport
     */
    public function current(?WeatherQuery $query = null): WeatherReport
    {
        return $this->driver()->current($query ?? $this->defaultQuery());
    }

    /**
     * 文字列設定を返す
     *
     * @param string $key
     * @param string $default
     *
     * @return string
     */
    private function stringConfig(string $key, string $default): string
    {
        $value = config($key, $default);

        if (! is_string($value) || $value === '') {
            return $default;
        }

        return $value;
    }

    /**
     * nullable な文字列設定を返す
     *
     * @param string $key
     *
     * @return string|null
     */
    private function nullableStringConfig(string $key): ?string
    {
        $value = config($key);

        if (! is_string($value) || $value === '') {
            return null;
        }

        return $value;
    }

    /**
     * 整数設定を返す
     *
     * @param string $key
     * @param int $default
     *
     * @return int
     */
    private function intConfig(string $key, int $default): int
    {
        $value = config($key, $default);

        if (! is_int($value)) {
            return $default;
        }

        return $value;
    }
}
