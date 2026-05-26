<?php

namespace App\Weather;

use Carbon\CarbonImmutable;

/**
 * Provider Response から正規化した天気情報
 */
class WeatherReport
{
    /** @var string provider 名 */
    public string $providerName;

    /** @var float 緯度 */
    public float $latitude;

    /** @var float 経度 */
    public float $longitude;

    /** @var string|null 表示用の地点名 */
    public ?string $locationName;

    /** @var string|null timezone */
    public ?string $timezone;

    /** @var float|null 現在気温 */
    public ?float $temperature;

    /** @var float|null 体感気温 */
    public ?float $apparentTemperature;

    /** @var float|null 降水量 */
    public ?float $precipitationAmount;

    /** @var float|null 雨量 */
    public ?float $rainAmount;

    /** @var float|null 降水確率 */
    public ?float $precipitationProbability;

    /** @var int|string|null 天気 condition/code */
    public int|string|null $weatherConditionCode;

    /** @var float|null 風速 */
    public ?float $windSpeed;

    /** @var CarbonImmutable|null 観測または予報時刻 */
    public ?CarbonImmutable $reportedAt;

    /** @var CarbonImmutable 取得時刻 */
    public CarbonImmutable $fetchedAt;

    /** @var string|null provider attribution または source label */
    public ?string $sourceLabel;

    /**
     * Constructor.
     *
     * @param string $providerName
     * @param float $latitude
     * @param float $longitude
     * @param string|null $locationName
     * @param string|null $timezone
     * @param float|null $temperature
     * @param float|null $apparentTemperature
     * @param float|null $precipitationAmount
     * @param float|null $rainAmount
     * @param float|null $precipitationProbability
     * @param int|string|null $weatherConditionCode
     * @param float|null $windSpeed
     * @param CarbonImmutable|null $reportedAt
     * @param CarbonImmutable $fetchedAt
     * @param string|null $sourceLabel
     */
    public function __construct(
        string $providerName,
        float $latitude,
        float $longitude,
        ?string $locationName,
        ?string $timezone,
        ?float $temperature,
        ?float $apparentTemperature,
        ?float $precipitationAmount,
        ?float $rainAmount,
        ?float $precipitationProbability,
        int|string|null $weatherConditionCode,
        ?float $windSpeed,
        ?CarbonImmutable $reportedAt,
        CarbonImmutable $fetchedAt,
        ?string $sourceLabel,
    ) {
        $this->providerName = $providerName;
        $this->latitude = $latitude;
        $this->longitude = $longitude;
        $this->locationName = $locationName;
        $this->timezone = $timezone;
        $this->temperature = $temperature;
        $this->apparentTemperature = $apparentTemperature;
        $this->precipitationAmount = $precipitationAmount;
        $this->rainAmount = $rainAmount;
        $this->precipitationProbability = $precipitationProbability;
        $this->weatherConditionCode = $weatherConditionCode;
        $this->windSpeed = $windSpeed;
        $this->reportedAt = $reportedAt;
        $this->fetchedAt = $fetchedAt;
        $this->sourceLabel = $sourceLabel;
    }
}
