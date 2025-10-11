<?php

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

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'facebook' => [
        'client_id' => env('FACEBOOK_CLIENT_ID'),
        'client_secret' => env('FACEBOOK_CLIENT_SECRET'),
        'redirect' => env('FACEBOOK_REDIRECT_URI', '/auth/facebook/callback'),
        'page_id' => env('FACEBOOK_PAGE_ID'),
        'page_access_token' => env('FACEBOOK_PAGE_ACCESS_TOKEN'),
        'access_token' => env('FACEBOOK_ACCESS_TOKEN'), // fallback
        'app_secret' => env('FACEBOOK_APP_SECRET'),
        'api_version' => env('FACEBOOK_GRAPH_VERSION', 'v18.0'),
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI', '/auth/google/callback'),
        'youtube_scope' => 'https://www.googleapis.com/auth/youtube.upload https://www.googleapis.com/auth/youtube.readonly',
    ],

    'instagram' => [
        'client_id' => env('FACEBOOK_CLIENT_ID'), // Instagram uses Facebook OAuth
        'client_secret' => env('FACEBOOK_CLIENT_SECRET'),
        'redirect' => env('INSTAGRAM_REDIRECT_URI', '/auth/instagram/callback'),
        'access_token' => env('INSTAGRAM_ACCESS_TOKEN'),
        'business_account_id' => env('INSTAGRAM_BUSINESS_ACCOUNT_ID'),
        'api_version' => env('INSTAGRAM_GRAPH_VERSION', 'v18.0'),
    ],

    'telegram' => [
        'bot_token' => env('TELEGRAM_BOT_TOKEN'),
        'chat_id' => env('TELEGRAM_CHAT_ID'),
        'channel_id' => env('TELEGRAM_CHANNEL_ID'),
        'bot_username' => env('TELEGRAM_BOT_USERNAME'),
        'webhook_url' => env('TELEGRAM_WEBHOOK_URL'),
    ],

    'n8n' => [
        'webhook_url' => env('N8N_WEBHOOK_URL'),
        'api_key' => env('N8N_API_KEY'),
        'base_url' => env('N8N_BASE_URL'),
    ],
];
