<?php

$weatherDefaultLatitude = env('RADIOPIPE_WEATHER_DEFAULT_LATITUDE');
$weatherDefaultLongitude = env('RADIOPIPE_WEATHER_DEFAULT_LONGITUDE');
$rssFeeds = env('RADIOPIPE_RSS_FEEDS', '');

return [
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
        'content_type_scores' => [
            'research_article' => 85,
            'technical_article' => 85,
            'data_analysis_article' => 85,
            'technical_blog_post' => 80,
            'news_article' => 70,
            'news' => 70,
            'opinion_essay' => 55,
            'personal_blog_post' => 55,
            'project_page' => 50,
            'landing_page' => 25,
            'news_article_headline_only' => 20,
            'support_question' => 20,
            'privacy_policy' => 10,
            'unknown' => 45,
        ],
        'penalties' => [
            'limitation_keyword' => 30,
        ],
        'limitation_keywords' => [
            'headline only',
            'title only',
            'only a headline',
            'no body',
            'missing body',
            'incomplete',
            'truncated',
            'not independently verified',
            'unverified',
            'speculative',
            'subjective',
            'promotional',
            'landing page',
            'extraction failed',
            'insufficient context',
        ],
        'sensitive_keywords' => [
            'disaster',
            'accident',
            'crime',
            'war',
            'military',
            'terrorism',
            'politics',
            'election',
            'medical',
            'health',
            'finance',
            'investment',
            'self-harm',
            'sexual',
            'abuse',
            'violence',
            'hate',
            'discrimination',
            'personal data',
            'credential leak',
            'security breach',
            'exploit',
        ],
    ],

    'topic_editorial' => [
        'analyzer' => env('RADIOPIPE_TOPIC_EDITORIAL_ANALYZER', 'fake'),
        'model' => env('RADIOPIPE_TOPIC_EDITORIAL_MODEL', 'gpt-5.4-mini'),
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
