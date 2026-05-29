<?php

$weatherDefaultLatitude = env('RADIOPIPE_WEATHER_DEFAULT_LATITUDE');
$weatherDefaultLongitude = env('RADIOPIPE_WEATHER_DEFAULT_LONGITUDE');
$rssFeeds = env('RADIOPIPE_RSS_FEEDS', '');
$adminAllowedEmails = env('RADIOPIPE_ADMIN_ALLOWED_EMAILS', '');

return [
    'admin' => [
        'allowed_emails' => array_values(array_filter(
            array_map('trim', explode(',', is_string($adminAllowedEmails) ? $adminAllowedEmails : '')),
            static fn (string $email): bool => $email !== '',
        )),
        'dev_login' => [
            'enabled' => (bool) env('RADIOPIPE_ADMIN_DEV_LOGIN_ENABLED', false),
            'email' => env('RADIOPIPE_ADMIN_DEV_LOGIN_EMAIL'),
        ],
    ],

    'upstream' => [
        'provider' => env('RADIOPIPE_UPSTREAM_PROVIDER', 'fake'),
        'url' => env('RADIOPIPE_UPSTREAM_URL'),
        'key' => env('RADIOPIPE_UPSTREAM_KEY'),
        'request_timeout' => (int) env('RADIOPIPE_UPSTREAM_REQUEST_TIMEOUT', 30),
        'max_retries' => (int) env('RADIOPIPE_UPSTREAM_MAX_RETRIES', 2),
        'default_window_hours' => (int) env('RADIOPIPE_UPSTREAM_DEFAULT_WINDOW_HOURS', 24),
        'default_limit' => (int) env('RADIOPIPE_UPSTREAM_DEFAULT_LIMIT', 100),
    ],

    'topic_screening' => [
        'weights' => [
            'freshness' => 0.25,
            'importance' => 0.35,
            'confidence' => 0.25,
            'content_type' => 0.15,
        ],
        'thresholds' => [
            'uncertain_confidence_score' => 45,
            'strong_limitation_penalty' => 30,
            'low_value_score' => 45,
        ],
        'importance_scores' => [
            5 => 100,
            4 => 80,
            3 => 60,
            2 => 30,
            1 => 10,
        ],
        // @todo Provisional until digestpipe defines and enforces a stable content_type taxonomy.
        'content_type_scores' => [
            'unknown' => 50,
        ],
    ],

    'topic_editorial' => [
        'analyzer' => env('RADIOPIPE_TOPIC_EDITORIAL_ANALYZER', 'fake'),
        'model' => env('RADIOPIPE_TOPIC_EDITORIAL_MODEL', 'gpt-5.4-mini'),
    ],

    'scenario' => [
        'generator' => env('RADIOPIPE_SCENARIO_GENERATOR', 'fake'),
        'model' => env('RADIOPIPE_SCENARIO_MODEL', 'gpt-5.4-mini'),
        'max_topics' => (int) env('RADIOPIPE_SCENARIO_MAX_TOPICS', 5),
        'target_seconds' => (int) env('RADIOPIPE_SCENARIO_TARGET_SECONDS', 900),
    ],

    'topic_nomination' => [
        'throttle_seconds' => (int) env('RADIOPIPE_TOPIC_NOMINATION_THROTTLE_SECONDS', 3600),
    ],

    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'request_timeout' => (int) env('OPENAI_REQUEST_TIMEOUT', 60),
        'max_retries' => (int) env('OPENAI_MAX_RETRIES', 2),
    ],

    'news' => [
        'provider' => env('RADIOPIPE_NEWS_PROVIDER', 'fake'),
        'request_timeout' => (int) env('RADIOPIPE_NEWS_REQUEST_TIMEOUT', 10),
        'max_retries' => (int) env('RADIOPIPE_NEWS_MAX_RETRIES', 2),
    ],

    'newsapi' => [
        'base_url' => env('RADIOPIPE_NEWSAPI_BASE_URL', 'https://newsapi.org'),
        'api_key' => env('RADIOPIPE_NEWSAPI_KEY'),
        'country' => env('RADIOPIPE_NEWSAPI_COUNTRY', 'jp'),
        'category' => env('RADIOPIPE_NEWSAPI_CATEGORY', 'general'),
        'language' => env('RADIOPIPE_NEWSAPI_LANGUAGE', 'ja'),
        'page_size' => (int) env('RADIOPIPE_NEWSAPI_PAGE_SIZE', 20),
        'sources' => env('RADIOPIPE_NEWSAPI_SOURCES'),
    ],

    'rss' => [
        'feed_urls' => array_values(array_filter(
            array_map('trim', explode(',', is_string($rssFeeds) ? $rssFeeds : '')),
            static fn (string $feedUrl): bool => $feedUrl !== '',
        )),
    ],

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
