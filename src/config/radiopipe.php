<?php

$weatherDefaultLatitude = env('RADIOPIPE_WEATHER_DEFAULT_LATITUDE');
$weatherDefaultLongitude = env('RADIOPIPE_WEATHER_DEFAULT_LONGITUDE');

return [
    'weather' => [
        'provider' => env('RADIOPIPE_WEATHER_PROVIDER', 'fake'),
        'request_timeout' => (int) env('RADIOPIPE_WEATHER_REQUEST_TIMEOUT', 10),
        'max_retries' => (int) env('RADIOPIPE_WEATHER_MAX_RETRIES', 2),

        'default' => [
            'latitude' => is_numeric($weatherDefaultLatitude) ? (float) $weatherDefaultLatitude : null,
            'longitude' => is_numeric($weatherDefaultLongitude) ? (float) $weatherDefaultLongitude : null,
            'location_name' => env('RADIOPIPE_WEATHER_DEFAULT_LOCATION_NAME'),
            'timezone' => env('RADIOPIPE_WEATHER_DEFAULT_TIMEZONE', 'Asia/Tokyo'),
        ],
    ],

    'open_meteo' => [
        'base_url' => env('RADIOPIPE_OPEN_METEO_BASE_URL', 'https://api.open-meteo.com'),
    ],
];
