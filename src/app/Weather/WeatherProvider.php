<?php

namespace App\Weather;

/**
 * 天気情報を取得して内部形式へ正規化する Provider
 */
interface WeatherProvider
{
    /**
     * 指定された地点の現在天気を取得して返す
     *
     * @param WeatherQuery $query
     *
     * @return WeatherReport
     *
     * @throws WeatherProviderException
     */
    public function current(WeatherQuery $query): WeatherReport;
}
