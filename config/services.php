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
        'page_id' => env('FACEBOOK_PAGE_ID'),
        'page_access_token' => env('FACEBOOK_PAGE_ACCESS_TOKEN'),
        'access_token' => env('FACEBOOK_ACCESS_TOKEN'), // fallback
        'app_secret' => env('FACEBOOK_APP_SECRET'),
        'api_version' => env('FACEBOOK_GRAPH_VERSION', 'v18.0'),
    ],

    'instagram' => [
        'access_token' => env('INSTAGRAM_ACCESS_TOKEN'),
        'business_account_id' => env('INSTAGRAM_BUSINESS_ACCOUNT_ID'),
        'api_version' => env('INSTAGRAM_GRAPH_VERSION', 'v18.0'),
    ],

    'telegram' => [
        'bot_token' => env('TELEGRAM_BOT_TOKEN'),
        'chat_id' => env('TELEGRAM_CHAT_ID'),
        'channel_id' => env('TELEGRAM_CHANNEL_ID'),
    ],

    'n8n' => [
        'webhook_url' => env('N8N_WEBHOOK_URL', 'http://65.2.142.106:5678/webhook/31a37984-c70a-447c-a9f0-765d1cf98d13'),
        'api_key' => env('N8N_API_KEY', 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiIwZTA4NDEzNy1hMWJhLTQ1YzctYjQyOC0xYWE5ZmVkMGE0ZTUiLCJpc3MiOiJuOG4iLCJhdWQiOiJwdWJsaWMtYXBpIiwiaWF0IjoxNzU5ODMyMzI5LCJleHAiOjE3NjQ5OTcyMDB9.Di92YNJuSwLGBh__4nLTN2ARoxRDI9OxiAu3Jv8LjPo'),
    ],


];
