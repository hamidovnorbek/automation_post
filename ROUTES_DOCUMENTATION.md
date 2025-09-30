# Social Media Automation System Routes Documentation

This document provides comprehensive information about all available routes in the Laravel + Filament social media automation system.

## Table of Contents

- [Overview](#overview)
- [Web Routes](#web-routes)
- [API Routes](#api-routes)
- [Console Commands](#console-commands)
- [Webhooks](#webhooks)
- [Authentication](#authentication)
- [Rate Limiting](#rate-limiting)
- [Error Handling](#error-handling)
- [Testing](#testing)

## Overview

The system provides multiple interfaces for managing social media automation:

- **Web Interface**: Filament admin panel at `/admin`
- **API Endpoints**: RESTful API for external integrations
- **Console Commands**: CLI tools for management and debugging
- **Webhooks**: Endpoints for receiving platform notifications

## Web Routes

### Main Application Routes

#### `GET /`
- **Description**: Welcome page
- **Authentication**: None
- **Response**: HTML welcome page

### Testing Routes (Development Only)

All testing routes are available under the `/test` prefix:

#### `GET /test/instagram`
- **Description**: Test Instagram service connection and posting
- **Authentication**: None (development only)
- **Response**: JSON with test results

#### `GET /test/facebook`
- **Description**: Test Facebook service connection and posting
- **Authentication**: None (development only)
- **Response**: JSON with test results

#### `GET /test/telegram`
- **Description**: Test Telegram service connection and posting
- **Authentication**: None (development only)
- **Response**: JSON with test results

#### `GET /test/all-services`
- **Description**: Test all configured social media services
- **Authentication**: None (development only)
- **Response**: JSON with results for all services

### Debug Routes (Local Environment Only)

Available under the `/debug` prefix when `APP_ENV=local`:

#### `GET /debug/queue-status`
- **Description**: Check queue status and job counts
- **Response**: JSON with queue information

#### `GET /debug/config-check`
- **Description**: Verify social media service configuration
- **Response**: JSON with configuration status

#### `GET /debug/clear-cache`
- **Description**: Clear all application caches
- **Response**: JSON confirmation

#### `GET /debug/create-test-post`
- **Description**: Create and publish a test post
- **Response**: JSON with post creation results

## API Routes

All API routes are prefixed with `/api` and return JSON responses.

### Webhook Endpoints

#### `POST /api/webhook/social-post`
- **Description**: Receive webhooks from social media platforms
- **Authentication**: Signature verification (configurable)
- **Content-Type**: `application/json`
- **Response**: JSON confirmation
- **Middleware**: `VerifyWebhookSignature` (optional)

**Request Example:**
```json
{
  "event": "post_published",
  "platform": "facebook",
  "post_id": "123456",
  "external_id": "facebook_post_id_123",
  "timestamp": "2024-01-01T12:00:00Z"
}
```

### Posts Management

#### `GET /api/posts`
- **Description**: List all posts with pagination and filtering
- **Authentication**: API middleware
- **Query Parameters**:
  - `status` (string): Filter by post status
  - `platform` (string): Filter by platform
  - `from_date` (date): Filter posts from date
  - `to_date` (date): Filter posts to date
  - `per_page` (integer): Items per page (default: 15)

**Response Example:**
```json
{
  "status": "success",
  "data": {
    "current_page": 1,
    "data": [...],
    "total": 50,
    "per_page": 15
  }
}
```

#### `GET /api/posts/{id}`
- **Description**: Get specific post with full details
- **Authentication**: API middleware
- **Response**: Complete post information with publications

#### `GET /api/posts/{id}/status`
- **Description**: Get post publication status summary
- **Authentication**: API middleware
- **Response**: Status overview with platform breakdown

**Response Example:**
```json
{
  "status": "success",
  "data": {
    "id": 1,
    "title": "My Post",
    "status": "published",
    "progress": 100,
    "platforms": {
      "selected": ["facebook", "instagram", "telegram"],
      "published": ["facebook", "telegram"],
      "failed": ["instagram"],
      "publishing": []
    },
    "publications_summary": {
      "total": 3,
      "published": 2,
      "failed": 1,
      "publishing": 0,
      "pending": 0
    }
  }
}
```

#### `POST /api/posts/{id}/retry`
- **Description**: Retry failed publications for a post
- **Authentication**: API middleware
- **Response**: Retry confirmation with details

#### `POST /api/posts/{id}/republish`
- **Description**: Republish post to specified platforms
- **Authentication**: API middleware
- **Request Body**:
```json
{
  "platforms": ["facebook", "instagram"]
}
```

### Publications Management

#### `GET /api/publications`
- **Description**: List all publications with filtering
- **Authentication**: API middleware
- **Query Parameters**:
  - `platform` (string): Filter by platform
  - `status` (string): Filter by status
  - `from_date` (date): Filter from date
  - `to_date` (date): Filter to date
  - `per_page` (integer): Items per page (default: 20)

#### `GET /api/publications/stats`
- **Description**: Get publication statistics and success rates
- **Authentication**: API middleware
- **Response**: Comprehensive statistics by platform and status

**Response Example:**
```json
{
  "status": "success",
  "data": {
    "total_publications": 150,
    "by_platform": {
      "facebook": 50,
      "instagram": 50,
      "telegram": 50
    },
    "by_status": {
      "published": 140,
      "failed": 8,
      "publishing": 1,
      "pending": 1
    },
    "success_rate": {
      "facebook": 95.5,
      "instagram": 92.0,
      "telegram": 98.0
    }
  }
}
```

### System Status

#### `GET /api/health`
- **Description**: System health check endpoint
- **Authentication**: API middleware
- **Response**: System status and configuration check

**Response Example:**
```json
{
  "status": "healthy",
  "timestamp": "2024-01-01T12:00:00Z",
  "version": "1.0.0",
  "environment": "production",
  "database": {
    "connection": "sqlite",
    "status": "connected"
  },
  "queue": {
    "default_connection": "database",
    "pending_jobs": 5,
    "failed_jobs": 2
  },
  "services": {
    "facebook": "configured",
    "instagram": "configured", 
    "telegram": "not_configured"
  }
}
```

## Console Commands

Access via `php artisan [command]`:

### Service Testing

#### `php artisan social:test-all`
- **Description**: Test all configured social media services
- **Usage**: Validates API connections and credentials

#### `php artisan social:health-check`
- **Description**: Comprehensive system health check
- **Usage**: Verifies database, queue, configuration, and recent activity

### Management Commands

#### `php artisan social:stats`
- **Description**: Display publication statistics
- **Usage**: Shows success rates and activity by platform

#### `php artisan social:retry-failed`
- **Description**: Retry all failed publications
- **Usage**: Interactive command to retry failed posts

#### `php artisan social:cleanup`
- **Description**: Clean up old data
- **Usage**: Removes old failed jobs and publication data

#### `php artisan social:create-test-post`
- **Description**: Create a test post interactively
- **Usage**: Interactive prompt to create and publish test content

### Scheduled Commands

These run automatically via Laravel's scheduler:

- `social:publish-scheduled` - Every minute
- `social:cleanup` - Daily
- `queue:retry all` - Hourly

## Webhooks

### Platform-Specific Webhooks

Each platform can send webhooks to notify about events:

#### Facebook Webhooks
- **URL**: `/api/webhook/social-post`
- **Verification**: Uses `X-Hub-Signature-256` header
- **Secret**: Configured via `FACEBOOK_APP_SECRET`

#### Instagram Webhooks
- **URL**: `/api/webhook/social-post`
- **Verification**: Same as Facebook (shared app)

#### Telegram Webhooks
- **URL**: `/api/webhook/social-post`
- **Verification**: Uses `X-Telegram-Bot-Api-Secret-Token` header
- **Secret**: Configured via `TELEGRAM_WEBHOOK_SECRET`

### Webhook Security

- Signature verification is enabled by default in production
- Can be disabled in local environment via `APP_VERIFY_WEBHOOKS_LOCALLY=false`
- All webhook requests are logged for debugging

## Authentication

### Web Routes
- Public routes: `/`, `/test/*`, `/debug/*` (local only)
- Admin routes: Protected by Filament authentication

### API Routes
- Public: `/api/webhook/*`
- Protected: All other `/api/*` routes use `api` middleware
- Authentication method depends on your Laravel configuration

### Recommendations
- Use Laravel Sanctum for API token authentication
- Consider rate limiting for public endpoints
- Implement proper CORS configuration for frontend integrations

## Rate Limiting

### Default Limits
- API routes: Standard Laravel rate limiting
- Webhook endpoints: No rate limiting (trusted sources)
- Platform APIs: Respected via service implementations

### Platform-Specific Limits
- **Facebook**: 200 requests/hour, 4800 requests/day
- **Instagram**: 200 requests/hour, 25 posts/day
- **Telegram**: 30 requests/second, 20 messages/minute per chat

## Error Handling

### API Response Format
```json
{
  "status": "error",
  "message": "Error description",
  "code": "ERROR_CODE",
  "details": {...}
}
```

### Common Error Codes
- `401`: Unauthorized (invalid credentials)
- `403`: Forbidden (insufficient permissions)
- `404`: Not Found (resource doesn't exist)
- `422`: Validation Error (invalid input)
- `429`: Rate Limited (too many requests)
- `500`: Internal Server Error

### Logging
- All API requests are logged when `SOCIAL_MEDIA_LOG_REQUESTS=true`
- Errors are logged with full context
- Webhook events are always logged

## Testing

### Development Testing
1. Use `/test/*` routes to verify service connections
2. Check `/debug/config-check` for configuration validation
3. Create test posts via `/debug/create-test-post`

### API Testing with cURL

#### Test Webhook Endpoint
```bash
curl -X POST http://localhost:8000/api/webhook/social-post \
  -H "Content-Type: application/json" \
  -d '{"test": "webhook", "platform": "facebook"}'
```

#### Get Post Status
```bash
curl http://localhost:8000/api/posts/1/status
```

#### Retry Failed Publications
```bash
curl -X POST http://localhost:8000/api/posts/1/retry
```

#### Health Check
```bash
curl http://localhost:8000/api/health
```

### Environment Setup
1. Copy `.env.social-media.example` sections to your `.env`
2. Configure your social media API credentials
3. Set up AWS S3 for media hosting
4. Configure webhook URLs in platform settings

### Queue Worker
For production, run the queue worker:
```bash
php artisan queue:work --queue=social-media
```

### Scheduler
Add to your crontab:
```bash
* * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1
```

## Security Considerations

1. **Environment Variables**: Store all sensitive data in `.env`
2. **HTTPS**: Use HTTPS in production for webhook endpoints
3. **Signature Verification**: Enable webhook signature verification
4. **Rate Limiting**: Implement rate limiting for public APIs
5. **Input Validation**: All inputs are validated before processing
6. **Error Information**: Sensitive data is filtered from logs
7. **Access Control**: Implement proper authentication and authorization

## Support

For issues or questions:
1. Check the Laravel logs in `storage/logs/`
2. Use console commands for debugging
3. Test individual services with `/test/*` routes
4. Monitor API responses and error messages
5. Review platform-specific API documentation