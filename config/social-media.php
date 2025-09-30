<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Social Media Automation Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure settings for the social media automation system.
    | This includes publication settings, retry logic, and platform-specific
    | configurations.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Default Settings
    |--------------------------------------------------------------------------
    */
    
    'enabled' => env('SOCIAL_MEDIA_ENABLED', true),
    
    'default_platforms' => ['facebook', 'instagram', 'telegram'],
    
    'publish_immediately' => env('SOCIAL_MEDIA_PUBLISH_IMMEDIATELY', false),
    
    /*
    |--------------------------------------------------------------------------
    | Retry Configuration
    |--------------------------------------------------------------------------
    */
    
    'retry' => [
        'max_attempts' => env('SOCIAL_MEDIA_MAX_RETRY_ATTEMPTS', 3),
        'delay_minutes' => env('SOCIAL_MEDIA_RETRY_DELAY_MINUTES', 5),
        'backoff_multiplier' => env('SOCIAL_MEDIA_RETRY_BACKOFF_MULTIPLIER', 2),
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue Configuration
    |--------------------------------------------------------------------------
    */
    
    'queue' => [
        'connection' => env('SOCIAL_MEDIA_QUEUE_CONNECTION', 'database'),
        'name' => env('SOCIAL_MEDIA_QUEUE_NAME', 'social-media'),
        'timeout' => env('SOCIAL_MEDIA_QUEUE_TIMEOUT', 300), // 5 minutes
    ],

    /*
    |--------------------------------------------------------------------------
    | Webhook Configuration
    |--------------------------------------------------------------------------
    */
    
    'webhook' => [
        'enabled' => env('SOCIAL_MEDIA_WEBHOOK_ENABLED', true),
        'url' => env('SOCIAL_MEDIA_WEBHOOK_URL', 'http://localhost:5678/webhook/social-post'),
        'timeout' => env('SOCIAL_MEDIA_WEBHOOK_TIMEOUT', 10),
        'retry_attempts' => env('SOCIAL_MEDIA_WEBHOOK_RETRY_ATTEMPTS', 3),
        'verify_ssl' => env('SOCIAL_MEDIA_WEBHOOK_VERIFY_SSL', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Media Upload Configuration
    |--------------------------------------------------------------------------
    */
    
    'media' => [
        'max_file_size_mb' => env('SOCIAL_MEDIA_MAX_FILE_SIZE_MB', 50),
        'allowed_image_types' => ['jpg', 'jpeg', 'png', 'gif', 'webp'],
        'allowed_video_types' => ['mp4', 'mov', 'avi', 'wmv', 'flv'],
        'storage_disk' => env('SOCIAL_MEDIA_STORAGE_DISK', 's3'),
        'public_url_base' => env('SOCIAL_MEDIA_PUBLIC_URL_BASE', ''),
        
        // Image optimization
        'optimize_images' => env('SOCIAL_MEDIA_OPTIMIZE_IMAGES', true),
        'image_quality' => env('SOCIAL_MEDIA_IMAGE_QUALITY', 85),
        'max_image_width' => env('SOCIAL_MEDIA_MAX_IMAGE_WIDTH', 1920),
        'max_image_height' => env('SOCIAL_MEDIA_MAX_IMAGE_HEIGHT', 1080),
    ],

    /*
    |--------------------------------------------------------------------------
    | Platform-Specific Configuration
    |--------------------------------------------------------------------------
    */
    
    'platforms' => [
        
        'facebook' => [
            'enabled' => env('FACEBOOK_ENABLED', true),
            'api_version' => env('FACEBOOK_API_VERSION', 'v18.0'),
            'rate_limit' => [
                'requests_per_hour' => 200,
                'requests_per_day' => 4800,
            ],
            'media' => [
                'max_image_size_mb' => 4,
                'max_video_size_mb' => 1024,
                'supported_image_formats' => ['jpg', 'png', 'gif'],
                'supported_video_formats' => ['mp4', 'mov'],
            ],
            'text' => [
                'max_length' => 63206,
                'hashtag_limit' => 30,
            ],
            'features' => [
                'supports_scheduling' => true,
                'supports_multiple_images' => true,
                'supports_video' => true,
                'supports_stories' => false,
            ],
        ],

        'instagram' => [
            'enabled' => env('INSTAGRAM_ENABLED', true),
            'api_version' => env('INSTAGRAM_API_VERSION', 'v18.0'),
            'rate_limit' => [
                'requests_per_hour' => 200,
                'requests_per_day' => 4800,
                'posts_per_day' => 25, // Instagram Business API limit
            ],
            'media' => [
                'max_image_size_mb' => 8,
                'max_video_size_mb' => 100,
                'supported_image_formats' => ['jpg', 'png'],
                'supported_video_formats' => ['mp4', 'mov'],
                'min_resolution' => [320, 320],
                'max_resolution' => [1440, 1800],
                'aspect_ratio_tolerance' => 0.01,
            ],
            'text' => [
                'max_length' => 2200,
                'hashtag_limit' => 30,
            ],
            'features' => [
                'supports_scheduling' => false, // Must publish immediately
                'supports_multiple_images' => true,
                'supports_video' => true,
                'supports_stories' => true,
                'requires_public_url' => true,
            ],
        ],

        'telegram' => [
            'enabled' => env('TELEGRAM_ENABLED', true),
            'rate_limit' => [
                'requests_per_second' => 30,
                'requests_per_minute' => 20,
                'messages_per_minute_per_chat' => 20,
            ],
            'media' => [
                'max_photo_size_mb' => 10,
                'max_video_size_mb' => 50,
                'max_document_size_mb' => 50,
                'supported_image_formats' => ['jpg', 'png', 'gif', 'webp'],
                'supported_video_formats' => ['mp4', 'avi', 'mov', 'mkv'],
            ],
            'text' => [
                'max_length' => 4096,
                'supports_html' => true,
                'supports_markdown' => true,
            ],
            'features' => [
                'supports_scheduling' => false,
                'supports_multiple_images' => true,
                'supports_video' => true,
                'supports_documents' => true,
                'supports_inline_keyboards' => true,
                'disable_notification' => env('TELEGRAM_DISABLE_NOTIFICATION', false),
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging Configuration
    |--------------------------------------------------------------------------
    */
    
    'logging' => [
        'enabled' => env('SOCIAL_MEDIA_LOGGING_ENABLED', true),
        'level' => env('SOCIAL_MEDIA_LOGGING_LEVEL', 'info'),
        'log_requests' => env('SOCIAL_MEDIA_LOG_REQUESTS', true),
        'log_responses' => env('SOCIAL_MEDIA_LOG_RESPONSES', false),
        'log_media_uploads' => env('SOCIAL_MEDIA_LOG_MEDIA_UPLOADS', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Testing and Development
    |--------------------------------------------------------------------------
    */
    
    'testing' => [
        'enabled' => env('SOCIAL_MEDIA_TESTING_ENABLED', false),
        'fake_responses' => env('SOCIAL_MEDIA_FAKE_RESPONSES', false),
        'test_webhook_url' => env('SOCIAL_MEDIA_TEST_WEBHOOK_URL'),
        'delay_publications' => env('SOCIAL_MEDIA_DELAY_PUBLICATIONS_SECONDS', 0),
    ],

    /*
    |--------------------------------------------------------------------------
    | Monitoring and Alerts
    |--------------------------------------------------------------------------
    */
    
    'monitoring' => [
        'enabled' => env('SOCIAL_MEDIA_MONITORING_ENABLED', true),
        'alert_on_failure' => env('SOCIAL_MEDIA_ALERT_ON_FAILURE', true),
        'alert_threshold' => env('SOCIAL_MEDIA_ALERT_THRESHOLD', 5), // failures per hour
        'health_check_interval' => env('SOCIAL_MEDIA_HEALTH_CHECK_INTERVAL', 300), // seconds
        'notification_channels' => [
            'email' => env('SOCIAL_MEDIA_ALERT_EMAIL'),
            'slack' => env('SOCIAL_MEDIA_ALERT_SLACK_WEBHOOK'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Configuration
    |--------------------------------------------------------------------------
    */
    
    'cache' => [
        'enabled' => env('SOCIAL_MEDIA_CACHE_ENABLED', true),
        'ttl' => [
            'access_tokens' => env('SOCIAL_MEDIA_CACHE_TOKEN_TTL', 3600), // 1 hour
            'rate_limits' => env('SOCIAL_MEDIA_CACHE_RATE_LIMIT_TTL', 300), // 5 minutes
            'api_responses' => env('SOCIAL_MEDIA_CACHE_API_RESPONSE_TTL', 60), // 1 minute
        ],
    ],
];