<?php

namespace App\Weather;

/**
 * 天気情報を取得して内部形式へ正規化する provider です。
 */
interface WeatherProvider
{
    /**
     * 指定された地点の現在天気を取得します。
     *
     * @throws WeatherProviderException
     */
    public function current(WeatherQuery $query): WeatherReport;
}
