<?php

namespace App\Weather;

use InvalidArgumentException;

/**
 * 天気取得対象の地点と表示用メタデータです。
 */
class WeatherQuery
{
    /** @var float 緯度 */
    public float $latitude;

    /** @var float 経度 */
    public float $longitude;

    /** @var string|null 表示用の地点名 */
    public ?string $locationName;

    /** @var string|null provider に渡す timezone */
    public ?string $timezone;

    /**
     * Constructor.
     */
    public function __construct(
        float $latitude,
        float $longitude,
        ?string $locationName = null,
        ?string $timezone = null,
    ) {
        if ($latitude < -90.0 || $latitude > 90.0) {
            throw new InvalidArgumentException('Weather latitude must be between -90 and 90.');
        }

        if ($longitude < -180.0 || $longitude > 180.0) {
            throw new InvalidArgumentException('Weather longitude must be between -180 and 180.');
        }

        $this->latitude = $latitude;
        $this->longitude = $longitude;
        $this->locationName = $locationName;
        $this->timezone = $timezone;
    }
}
