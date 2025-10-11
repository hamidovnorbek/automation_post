# Modern Social Media Connection System

## Overview

This system provides a modern, secure way to connect and manage social media accounts using Laravel Socialite with OAuth 2.0 authentication. It features a beautiful dashboard interface, automatic token refresh, connection testing, and seamless integration with your posting workflow.

## Features

### ✨ Modern Interface
- Beautiful glass-morphism design
- Real-time connection status
- Mobile-responsive dashboard
- Interactive connection cards

### 🔒 Secure Authentication
- OAuth 2.0 with Laravel Socialite
- Encrypted token storage
- Automatic token refresh
- Secure disconnection

### 📱 Supported Platforms
- **Facebook**: Pages and profiles
- **Instagram**: Business accounts
- **YouTube**: Channel management
- **Telegram**: Bot integration

### ⚡ Smart Features
- Real-time connection testing
- Token expiration warnings
- Automatic credential refresh
- Integration with n8n workflows

## Setup Instructions

### 1. Install Dependencies

Laravel Socialite is already installed. The system supports:

```bash
composer require laravel/socialite
```

### 2. Environment Configuration

Copy the example configuration to your `.env` file:

```bash
cp .env.social-example .env.additions
cat .env.additions >> .env
```

### 3. OAuth App Setup

#### Facebook/Instagram
1. Visit [Facebook Developers](https://developers.facebook.com/)
2. Create a new app → "Consumer" type
3. Add Facebook Login product
4. Configure Valid OAuth Redirect URIs:
   - `http://localhost:8000/social/callback/facebook`
   - `http://localhost:8000/social/callback/instagram`
5. Copy App ID and App Secret to `.env`

#### Google/YouTube
1. Visit [Google Cloud Console](https://console.cloud.google.com/)
2. Create new project or select existing
3. Enable YouTube Data API v3
4. Create OAuth 2.0 credentials
5. Add redirect URI: `http://localhost:8000/social/callback/google`
6. Copy Client ID and Secret to `.env`

#### Telegram
1. Message [@BotFather](https://t.me/botfather) on Telegram
2. Send `/newbot` and follow instructions
3. Copy bot token to `.env`
4. Add bot to your channel/group
5. Make bot an admin with posting permissions

### 4. Database Migration

The system uses the existing `social_accounts` table with additional fields:

```bash
php artisan migrate
```

## Usage

### Accessing the Dashboard

Visit the modern connection dashboard:
```
/social/connections
```

### Connecting Accounts

1. **OAuth Platforms** (Facebook, Instagram, YouTube):
   - Click "Connect [Platform]" button
   - Complete OAuth flow
   - Permissions are automatically requested

2. **Telegram**:
   - Click "Connect Bot" 
   - Enter bot token from @BotFather
   - Provide chat/channel ID
   - System validates bot connection

### Managing Connections

- **Test Connection**: Verify account is working
- **View Details**: See token expiration, permissions
- **Disconnect**: Remove account securely
- **Auto-refresh**: Tokens refreshed automatically

### Integration with Filament

The system includes:
- Filament resource for account management
- Stats widget showing connection status
- Integration with existing post management

## API Integration

### Sending Posts to n8n

The `SocialMediaService` automatically handles:

```php
use App\Services\SocialMediaService;

$service = new SocialMediaService();
$result = $service->sendPostToN8n($postData, $user);

if ($result['success']) {
    // Post sent to n8n with user credentials
    $platforms = $result['platforms_sent'];
} else {
    // Handle error
    $error = $result['error'];
}
```

### Credential Format

Credentials sent to n8n are formatted per platform:

```json
{
  "facebook": {
    "access_token": "token",
    "refresh_token": "refresh",
    "user_id": "12345",
    "expires_at": "2024-01-01T12:00:00Z"
  },
  "telegram": {
    "bot_token": "token",
    "chat_id": "@channel",
    "bot_username": "mybot"
  }
}
```

## Architecture

### Models
- `SocialAccount`: Stores encrypted credentials
- `User`: Extended with connection helpers

### Controllers
- `SocialAuthController`: Handles OAuth flow
- Replaces legacy OAuth implementation

### Services
- `SocialMediaService`: Updated for new system
- Handles token refresh and testing

### Views
- Modern dashboard with Tailwind CSS
- Telegram bot connection form
- Responsive design

## Security Features

### Token Encryption
All tokens stored encrypted using Laravel's Crypt facade:

```php
// Automatic encryption/decryption
$account->access_token = 'plain_token';
$decrypted = $account->access_token; // Auto-decrypted
```

### Token Refresh
Automatic refresh for OAuth tokens before expiration:

```php
$service = new SocialMediaService();
$service->refreshTokenIfNeeded($account);
```

### Connection Testing
Validate account status without posting:

```php
$result = $service->testConnection($account);
// Returns: ['success' => true/false, 'message' => '...']
```

## Routes

### Public Routes
- `GET /social/connections` - Dashboard
- `GET /social/connect/{provider}` - OAuth redirect
- `GET /social/callback/{provider}` - OAuth callback

### Telegram Routes
- `GET /social/telegram/form` - Bot setup form
- `POST /social/telegram/connect` - Connect bot

### Management Routes
- `GET /social/disconnect/{platform}` - Disconnect
- `GET /social/test/{platform}` - Test connection

## Filament Integration

### Admin Panel Features
- Social accounts resource (legacy view)
- Modern dashboard link
- Connection stats widget
- Empty state with setup instructions

### Widget
Dashboard widget shows:
- Active connections count
- Expiring tokens warning
- Connection health status

## Migration Guide

### From Legacy System

1. **Keep existing accounts**: Current social accounts remain functional
2. **Use new interface**: Modern dashboard provides better UX
3. **Token format**: Enhanced with provider IDs and metadata
4. **Backward compatibility**: Legacy routes still available

### Database Changes

New fields added to `social_accounts`:
- `provider_id`: External user/bot ID
- `provider_name`: Display name from provider

## Troubleshooting

### Common Issues

1. **OAuth Redirect Mismatch**
   - Ensure redirect URIs match exactly in provider settings
   - Check HTTPS vs HTTP for production

2. **Token Expiration**
   - System auto-refreshes but may need re-authentication
   - Check provider-specific token lifetimes

3. **Telegram Bot Issues**
   - Verify bot has admin permissions in target channel
   - Use numeric chat ID for private channels

4. **N8N Integration**
   - Check webhook URL configuration
   - Verify n8n is receiving credentials format

### Debug Mode

Enable detailed logging:

```php
// In .env
LOG_LEVEL=debug

// Check logs
tail -f storage/logs/laravel.log | grep -i social
```

## Development

### Adding New Platforms

1. Add platform to enum in migration:
```php
$table->enum('platform_name', ['facebook', 'instagram', 'telegram', 'youtube', 'new_platform']);
```

2. Add OAuth configuration to `config/services.php`
3. Implement handler in `SocialAuthController`
4. Add platform card to dashboard view
5. Update `SocialMediaService` for credential format

### Customization

The dashboard is highly customizable:
- Modify `/resources/views/social-connections/index.blade.php`
- Update platform configurations in controller
- Extend service classes for custom integrations

## Security Considerations

- All tokens encrypted at rest
- OAuth state validation
- CSRF protection on forms
- Input validation and sanitization
- Rate limiting on OAuth endpoints
- Secure token refresh flow

## Performance

- Minimal database queries
- Cached connection status
- Async token refresh
- Optimized frontend assets
- CDN-delivered dependencies

## Support

For issues or questions:
1. Check logs in `storage/logs/laravel.log`
2. Verify configuration in `.env`
3. Test OAuth app settings
4. Check provider API status pages