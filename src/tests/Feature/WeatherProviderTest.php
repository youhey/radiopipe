<?php

namespace Tests\Feature;

use App\Weather\FakeWeatherProvider;
use App\Weather\OpenMeteoWeatherProvider;
use App\Weather\WeatherProvider;
use App\Weather\WeatherProviderException;
use App\Weather\WeatherProviderManager;
use App\Weather\WeatherQuery;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * @internal
 */
class WeatherProviderTest extends TestCase
{
    public function testFakeProviderReturnsNormalizedWeatherReport(): void
    {
        $query = new WeatherQuery(
            latitude: 35.681236,
            longitude: 139.767125,
            locationName: 'Tokyo Station',
            timezone: 'Asia/Tokyo',
        );

        $report = (new FakeWeatherProvider())->current($query);

        self::assertSame('fake', $report->providerName);
        self::assertSame(35.681236, $report->latitude);
        self::assertSame(139.767125, $report->longitude);
        self::assertSame('Tokyo Station', $report->locationName);
        self::assertSame('Asia/Tokyo', $report->timezone);
        self::assertSame(22.5, $report->temperature);
        self::assertSame(23.0, $report->apparentTemperature);
        self::assertSame(0.0, $report->precipitationAmount);
        self::assertSame(0.0, $report->rainAmount);
        self::assertSame(10.0, $report->precipitationProbability);
        self::assertSame('fake_clear', $report->weatherConditionCode);
        self::assertSame(2.4, $report->windSpeed);
        self::assertSame('Fake weather provider', $report->sourceLabel);
        self::assertInstanceOf(CarbonImmutable::class, $report->reportedAt);
        self::assertInstanceOf(CarbonImmutable::class, $report->fetchedAt);
    }

    public function testWeatherProviderCanBeSelectedThroughConfig(): void
    {
        config(['radiopipe.weather.provider' => 'fake']);

        self::assertInstanceOf(FakeWeatherProvider::class, $this->app->make(WeatherProvider::class));

        config([
            'radiopipe.weather.provider' => 'open_meteo',
            'radiopipe.open_meteo.base_url' => 'https://api.open-meteo.test',
        ]);

        self::assertInstanceOf(OpenMeteoWeatherProvider::class, $this->app->make(WeatherProvider::class));
    }

    public function testWeatherProviderManagerBuildsDefaultQueryFromConfig(): void
    {
        config([
            'radiopipe.weather.default.latitude' => 35.681236,
            'radiopipe.weather.default.longitude' => 139.767125,
            'radiopipe.weather.default.location_name' => 'Tokyo Station',
            'radiopipe.weather.default.timezone' => 'Asia/Tokyo',
        ]);

        $query = $this->app->make(WeatherProviderManager::class)->defaultQuery();

        self::assertSame(35.681236, $query->latitude);
        self::assertSame(139.767125, $query->longitude);
        self::assertSame('Tokyo Station', $query->locationName);
        self::assertSame('Asia/Tokyo', $query->timezone);
    }

    public function testOpenMeteoProviderFetchesAndNormalizesWeatherReport(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-05-25 12:00:00', 'UTC'));

        config([
            'radiopipe.weather.provider' => 'open_meteo',
            'radiopipe.open_meteo.base_url' => 'https://api.open-meteo.test',
            'radiopipe.weather.request_timeout' => 7,
            'radiopipe.weather.max_retries' => 1,
        ]);

        Http::fake([
            'https://api.open-meteo.test/v1/forecast*' => Http::response([
                'latitude' => 35.7,
                'longitude' => 139.75,
                'timezone' => 'Asia/Tokyo',
                'current' => [
                    'time' => '2026-05-25T21:00',
                    'temperature_2m' => 24.3,
                    'apparent_temperature' => 25.1,
                    'precipitation' => 0.4,
                    'rain' => 0.2,
                    'weather_code' => 61,
                    'wind_speed_10m' => 4.8,
                ],
            ], 200),
        ]);

        try {
            $report = $this->app->make(WeatherProvider::class)->current(new WeatherQuery(
                latitude: 35.681236,
                longitude: 139.767125,
                locationName: 'Tokyo Station',
                timezone: 'Asia/Tokyo',
            ));

            self::assertSame('open_meteo', $report->providerName);
            self::assertSame(35.7, $report->latitude);
            self::assertSame(139.75, $report->longitude);
            self::assertSame('Tokyo Station', $report->locationName);
            self::assertSame('Asia/Tokyo', $report->timezone);
            self::assertSame(24.3, $report->temperature);
            self::assertSame(25.1, $report->apparentTemperature);
            self::assertSame(0.4, $report->precipitationAmount);
            self::assertSame(0.2, $report->rainAmount);
            self::assertNull($report->precipitationProbability);
            self::assertSame(61, $report->weatherConditionCode);
            self::assertSame(4.8, $report->windSpeed);
            self::assertSame('2026-05-25 21:00:00', $report->reportedAt?->toDateTimeString());
            self::assertSame('2026-05-25 12:00:00', $report->fetchedAt->toDateTimeString());
            self::assertSame('Open-Meteo forecast API', $report->sourceLabel);

            Http::assertSent(function (Request $request): bool {
                $url = $request->url();

                return str_starts_with($url, 'https://api.open-meteo.test/v1/forecast')
                    && str_contains($url, 'latitude=35.681236')
                    && str_contains($url, 'longitude=139.767125')
                    && str_contains($url, 'temperature_2m')
                    && str_contains($url, 'apparent_temperature')
                    && str_contains($url, 'weather_code')
                    && str_contains($url, 'wind_speed_10m')
                    && str_contains($url, 'timezone=Asia%2FTokyo');
            });
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function testOpenMeteoProviderThrowsForFailedHttpResponse(): void
    {
        config([
            'radiopipe.weather.provider' => 'open_meteo',
            'radiopipe.open_meteo.base_url' => 'https://api.open-meteo.test',
        ]);

        Http::fake([
            'https://api.open-meteo.test/v1/forecast*' => Http::response(['reason' => 'unavailable'], 503),
        ]);

        $this->expectException(WeatherProviderException::class);
        $this->expectExceptionMessage('Weather provider [open_meteo] returned HTTP status [503].');

        $this->app->make(WeatherProvider::class)->current(new WeatherQuery(
            latitude: 35.681236,
            longitude: 139.767125,
        ));
    }
}
