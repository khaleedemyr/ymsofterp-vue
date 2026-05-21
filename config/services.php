<?php

use App\Support\MetaPageTokens;

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI'),
        'places_api_key' => env('GOOGLE_PLACES_API_KEY'),
    ],

    'recaptcha' => [
        'site_key' => env('RECAPTCHA_SITE_KEY'),
        'secret_key' => env('RECAPTCHA_SECRET_KEY'),
        'min_score' => env('RECAPTCHA_MIN_SCORE', 0.5),
        'timeout' => env('RECAPTCHA_TIMEOUT', 5),
    ],

    'meta' => [
        'app_id' => env('META_APP_ID'),
        'app_secret' => trim((string) env('META_APP_SECRET', '')),
        'whatsapp_access_token' => env('META_WHATSAPP_ACCESS_TOKEN'),
        'whatsapp_phone_number_id' => env('META_WHATSAPP_PHONE_NUMBER_ID'),
        'whatsapp_business_account_id' => env('META_WHATSAPP_BUSINESS_ACCOUNT_ID'),
        'page_access_token' => env('META_PAGE_ACCESS_TOKEN'),
        'page_id' => env('META_PAGE_ID'),
        /** @var array<string, string> page_id => page_access_token (multi Page Messenger) */
        'page_tokens' => MetaPageTokens::parse((string) env('META_PAGE_TOKENS', '{}')),
        'instagram_account_id' => env('META_INSTAGRAM_ACCOUNT_ID'),
        'graph_api_version' => env('META_GRAPH_API_VERSION', 'v25.0'),
        'webhook_verify_token' => env('META_WEBHOOK_VERIFY_TOKEN'),
        'webhook_skip_signature_verify' => env('META_WEBHOOK_SKIP_SIGNATURE_VERIFY', false),
    ],

    'fcm' => [
        // Legacy API (deprecated - tidak bisa digunakan lagi)
        'server_key' => env('FCM_SERVER_KEY'), // Default/fallback key (can be used for both iOS and Android)
        'ios_key' => env('FCM_IOS_KEY', env('FCM_SERVER_KEY')), // Falls back to server_key if not set
        'android_key' => env('FCM_ANDROID_KEY', env('FCM_SERVER_KEY')), // Falls back to server_key if not set
        
        // HTTP v1 API (recommended - menggunakan Service Account JSON)
        'service_account_path' => env('FCM_SERVICE_ACCOUNT_PATH'), // Path ke Service Account JSON file
        'project_id' => env('FCM_PROJECT_ID'), // Firebase Project ID
        'use_v1_api' => env('FCM_USE_V1_API', true), // Use HTTP v1 API (default: true)
    ],

];
