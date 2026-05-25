<?php

namespace App\Weather;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;

/**
 * Open-Meteo forecast API から現在天気を取得する provider です。
 */
class OpenMeteoWeatherProvider implements WeatherProvider
{
    private string $baseUrl;

    private int $timeout;

    private int $maxRetries;

    /**
     * Constructor.
     */
    public function __construct(string $baseUrl, int $timeout, int $maxRetries)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->timeout = $timeout;
        $this->maxRetries = $maxRetries;
    }

    /**
     * Open-Meteo response を内部の天気形式へ正規化します。
     */
    public function current(WeatherQuery $query): WeatherReport
    {
        $response = Http::baseUrl($this->baseUrl)
            ->acceptJson()
            ->timeout($this->timeout)
            ->retry($this->maxRetries, 100, null, false)
            ->get('/v1/forecast', [
                'latitude' => $query->latitude,
                'longitude' => $query->longitude,
                'current' => implode(',', [
                    'temperature_2m',
                    'apparent_temperature',
                    'precipitation',
                    'rain',
                    'weather_code',
                    'wind_speed_10m',
                ]),
                'timezone' => $query->timezone ?? 'auto',
            ]);

        if ($response->failed()) {
            throw WeatherProviderException::failedHttpResponse('open_meteo', $response->status());
        }

        $payload = $response->json();

        if (! is_array($payload)) {
            throw WeatherProviderException::invalidResponse('open_meteo');
        }

        $current = $payload['current'] ?? null;

        if (! is_array($current)) {
            throw WeatherProviderException::invalidResponse('open_meteo');
        }

        return new WeatherReport(
            providerName: 'open_meteo',
            latitude: $this->floatValue($payload['latitude'] ?? null) ?? $query->latitude,
            longitude: $this->floatValue($payload['longitude'] ?? null) ?? $query->longitude,
            locationName: $query->locationName,
            timezone: $this->stringValue($payload['timezone'] ?? null) ?? $query->timezone,
            temperature: $this->floatValue($current['temperature_2m'] ?? null),
            apparentTemperature: $this->floatValue($current['apparent_temperature'] ?? null),
            precipitationAmount: $this->floatValue($current['precipitation'] ?? null),
            rainAmount: $this->floatValue($current['rain'] ?? null),
            precipitationProbability: null,
            weatherConditionCode: $this->conditionCode($current['weather_code'] ?? null),
            windSpeed: $this->floatValue($current['wind_speed_10m'] ?? null),
            reportedAt: $this->reportedAt($current['time'] ?? null, $this->stringValue($payload['timezone'] ?? null)),
            fetchedAt: CarbonImmutable::now('UTC'),
            sourceLabel: 'Open-Meteo forecast API',
        );
    }

    /**
     * 数値として扱える provider 値を float に正規化します。
     */
    private function floatValue(mixed $value): ?float
    {
        if (! is_int($value) && ! is_float($value) && ! is_string($value)) {
            return null;
        }

        if (! is_numeric($value)) {
            return null;
        }

        return (float) $value;
    }

    /**
     * 空文字ではない文字列値を返します。
     */
    private function stringValue(mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        return $value;
    }

    /**
     * Open-Meteo の weather_code を整数に正規化します。
     */
    private function conditionCode(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && ctype_digit($value)) {
            return (int) $value;
        }

        return null;
    }

    /**
     * provider の時刻文字列を CarbonImmutable へ変換します。
     */
    private function reportedAt(mixed $value, ?string $timezone): ?CarbonImmutable
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        return CarbonImmutable::parse($value, $timezone);
    }
}
