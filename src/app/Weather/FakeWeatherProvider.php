<?php

namespace App\Weather;

use Carbon\CarbonImmutable;

/**
 * テストとローカル開発用の固定天気 provider です。
 */
class FakeWeatherProvider implements WeatherProvider
{
    /**
     * 指定地点に対する deterministic な fake 天気を返します。
     */
    public function current(WeatherQuery $query): WeatherReport
    {
        $now = CarbonImmutable::now('UTC');

        return new WeatherReport(
            providerName: 'fake',
            latitude: $query->latitude,
            longitude: $query->longitude,
            locationName: $query->locationName,
            timezone: $query->timezone,
            temperature: 22.5,
            apparentTemperature: 23.0,
            precipitationAmount: 0.0,
            rainAmount: 0.0,
            precipitationProbability: 10.0,
            weatherConditionCode: 'fake_clear',
            windSpeed: 2.4,
            reportedAt: $now,
            fetchedAt: $now,
            sourceLabel: 'Fake weather provider',
        );
    }
}
