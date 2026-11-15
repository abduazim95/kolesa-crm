<?php

return [
    'base_url_app' => env('KOLESA_BASE_URL_APP', 'https://app.kolesa.kz'),
    'base_url_api' => env('KOLESA_BASE_URL_API', 'https://api.kolesa.kz'),

    'version_prefix' => 'v9.1',

    'sleep_seconds' => (int) env('KOLESA_SLEEP_SECONDS', 3),
    'services_sleep_ms' => (int) env('KOLESA_SERVICES_SLEEP_MS', 1500),
    'page_size' => 20,
    'queue_name' => 'kolesa-sync',
    'queue_packages' => 'kolesa-packages',
    'request_timeout' => 30,

    'query_defaults' => [
        'screen' => 'cabinet_list',
        'text' => '',
        'chipsPackages' => 1,
        'webPackages' => 'true',
        'abWithLLSS' => 1,
        'appId' => env('KOLESA_APP_ID'),
        'appKey' => env('KOLESA_APP_KEY'),
    ],

    'headers' => [
        'User-Agent' => env('KOLESA_HDR_USER_AGENT', 'okhttp/4.12.0'),
        'app-version' => env('KOLESA_HDR_APP_VERSION'),
        'app-language' => env('KOLESA_HDR_APP_LANGUAGE', 'ru'),
        'app-platform' => env('KOLESA_HDR_APP_PLATFORM'),
        'app-platform-version' => env('KOLESA_HDR_APP_PLATFORM_VERSION'),
        'app-push-enable' => env('KOLESA_HDR_APP_PUSH_ENABLE', 'true'),
        'app-screen-coefficient' => env('KOLESA_HDR_APP_SCREEN_COEFFICIENT'),
        'X-App-Screen-Coefficient' => env('KOLESA_HDR_APP_SCREEN_COEFFICIENT'),
        'app-device-name' => env('KOLESA_HDR_APP_DEVICE_NAME'),
        'X-PHONE-ID' => env('KOLESA_HDR_PHONE_ID'),
        'gaid' => env('KOLESA_HDR_GAID'),
        'app-version-code' => env('KOLESA_HDR_APP_VERSION_CODE'),
        'APP-PHOTO-FORMAT' => env('KOLESA_HDR_APP_PHOTO_FORMAT', 'webp'),
        'x-current-user' => env('KOLESA_HDR_X_CURRENT_USER'),
    ],

    'cookie' => env('KOLESA_COOKIE'),
];
