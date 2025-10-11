<?php

namespace App\Services;

use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class SocialMediaService
{
    /**
     * Send post data to n8n with user's social media credentials
     */
    public function sendPostToN8n(array $postData, User $user): array
    {
        $credentials = $this->getUserCredentials($user, $postData['social_medias'] ?? []);

        $payload = [
            'user_id' => $user->id,
            'post' => $postData,
            'credentials' => $credentials,
            'timestamp' => now()->toISOString(),
            'platform_count' => count($credentials),
        ];

        try {
            $response = Http::timeout(30)
                ->post(config('services.n8n.webhook_url'), $payload);

            if ($response->successful()) {
                Log::info('Post sent to n8n successfully', [
                    'user_id' => $user->id,
                    'post_id' => $postData['id'] ?? null,
                    'platforms' => array_keys($credentials),
                    'webhook_response' => $response->json()
                ]);

                return [
                    'success' => true,
                    'response' => $response->json(),
                    'platforms_sent' => array_keys($credentials),
                ];
            } else {
                throw new \Exception('n8n responded with status: ' . $response->status());
            }

        } catch (\Exception $e) {
            Log::error('Failed to send post to n8n', [
                'error' => $e->getMessage(),
                'user_id' => $user->id,
                'post_id' => $postData['id'] ?? null,
                'intended_platforms' => $postData['social_medias'] ?? [],
                'available_credentials' => array_keys($credentials),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'platforms_attempted' => array_keys($credentials),
            ];
        }
    }

    /**
     * Get user's credentials for specified platforms
     */
    public function getUserCredentials(User $user, array $platforms): array
    {
        $credentials = [];

        foreach ($platforms as $platform) {
            $account = $user->getSocialAccount($platform);

            if ($account && $account->is_active) {
                // Refresh token if needed
                if ($account->isExpiringSoon()) {
                    $this->refreshTokenIfNeeded($account);
                }

                $credentials[$platform] = $this->formatCredentialsForPlatform($account);
            }
        }

        return $credentials;
    }

    /**
     * Format credentials for specific platform
     */
    private function formatCredentialsForPlatform(SocialAccount $account): array
    {
        $baseCredentials = [
            'access_token' => $account->access_token,
            'expires_at' => $account->expires_at?->toISOString(),
            'account_username' => $account->account_username,
        ];

        return match($account->platform_name) {
            'facebook' => array_merge($baseCredentials, [
                'refresh_token' => $account->refresh_token,
                'user_id' => $account->meta['user_id'] ?? null,
            ]),
            'instagram' => array_merge($baseCredentials, [
                'refresh_token' => $account->refresh_token,
                'user_id' => $account->meta['user_id'] ?? null,
            ]),
            'youtube' => array_merge($baseCredentials, [
                'refresh_token' => $account->refresh_token,
                'channel_id' => $account->meta['channel_id'] ?? null,
            ]),
            'telegram' => [
                'bot_token' => $account->access_token,
                'chat_id' => $account->meta['chat_id'] ?? null,
                'bot_username' => $account->account_username,
            ],
            default => $baseCredentials,
        };
    }

    /**
     * Refresh OAuth token if needed
     */
    public function refreshTokenIfNeeded(SocialAccount $account): bool
    {
        if (!$account->refresh_token || !$account->isExpiringSoon()) {
            return false;
        }

        try {
            $newTokens = match($account->platform_name) {
                'facebook', 'instagram' => $this->refreshFacebookToken($account),
                'youtube' => $this->refreshGoogleToken($account),
                default => null,
            };

            if ($newTokens) {
                $account->update([
                    'access_token' => $newTokens['access_token'],
                    'refresh_token' => $newTokens['refresh_token'] ?? $account->refresh_token,
                    'expires_at' => isset($newTokens['expires_in'])
                        ? Carbon::now()->addSeconds($newTokens['expires_in'])
                        : $account->expires_at,
                ]);

                Log::info('Token refreshed successfully', [
                    'platform' => $account->platform_name,
                    'user_id' => $account->user_id,
                ]);

                return true;
            }

        } catch (\Exception $e) {
            Log::error('Failed to refresh token', [
                'platform' => $account->platform_name,
                'user_id' => $account->user_id,
                'error' => $e->getMessage(),
            ]);

            // Deactivate account if refresh fails
            $account->update(['is_active' => false]);
        }

        return false;
    }

    /**
     * Refresh Facebook/Instagram token
     */
    private function refreshFacebookToken(SocialAccount $account): ?array
    {
        $response = Http::post('https://graph.facebook.com/v18.0/oauth/access_token', [
            'grant_type' => 'refresh_token',
            'refresh_token' => $account->refresh_token,
            'client_id' => config('services.facebook.client_id'),
            'client_secret' => config('services.facebook.client_secret'),
        ]);

        $data = $response->json();

        if (isset($data['error'])) {
            throw new \Exception($data['error']['message'] ?? 'Facebook token refresh failed');
        }

        return $data;
    }

    /**
     * Refresh Google/YouTube token
     */
    private function refreshGoogleToken(SocialAccount $account): ?array
    {
        $response = Http::post('https://oauth2.googleapis.com/token', [
            'grant_type' => 'refresh_token',
            'refresh_token' => $account->refresh_token,
            'client_id' => config('services.google.client_id'),
            'client_secret' => config('services.google.client_secret'),
        ]);

        $data = $response->json();

        if (isset($data['error'])) {
            throw new \Exception($data['error_description'] ?? 'Google token refresh failed');
        }

        return $data;
    }

    /**
     * Get connection status for all platforms for a user
     */
    public function getConnectionStatus(User $user): array
    {
        $platforms = ['facebook', 'instagram', 'telegram', 'youtube'];
        $status = [];

        foreach ($platforms as $platform) {
            $account = $user->getSocialAccount($platform);

            $status[$platform] = [
                'connected' => $account !== null,
                'active' => $account?->is_active ?? false,
                'expired' => $account?->isExpired() ?? false,
                'expiring_soon' => $account?->isExpiringSoon() ?? false,
                'username' => $account?->account_username,
                'connected_at' => $account?->created_at?->toDateTimeString(),
            ];
        }

        return $status;
    }

    /**
     * Test connection for a specific platform
     */
    public function testConnection(SocialAccount $account): array
    {
        try {
            $result = match($account->platform_name) {
                'facebook' => $this->testFacebookConnection($account),
                'instagram' => $this->testInstagramConnection($account),
                'youtube' => $this->testYouTubeConnection($account),
                'telegram' => $this->testTelegramConnection($account),
                default => ['success' => false, 'message' => 'Platform not supported'],
            };

            // Update account status based on test result
            if (!$result['success'] && $account->is_active) {
                $account->update(['is_active' => false]);
            }

            return $result;

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Connection test failed: ' . $e->getMessage(),
            ];
        }
    }

    private function testFacebookConnection(SocialAccount $account): array
    {
        $response = Http::get('https://graph.facebook.com/me', [
            'access_token' => $account->access_token,
            'fields' => 'id,name',
        ]);

        if ($response->successful()) {
            return ['success' => true, 'message' => 'Facebook connection is working'];
        }

        return ['success' => false, 'message' => 'Facebook connection failed'];
    }

    private function testInstagramConnection(SocialAccount $account): array
    {
        // Instagram uses Facebook Graph API
        return $this->testFacebookConnection($account);
    }

    private function testYouTubeConnection(SocialAccount $account): array
    {
        $response = Http::get('https://www.googleapis.com/youtube/v3/channels', [
            'access_token' => $account->access_token,
            'part' => 'snippet',
            'mine' => 'true',
        ]);

        if ($response->successful()) {
            return ['success' => true, 'message' => 'YouTube connection is working'];
        }

        return ['success' => false, 'message' => 'YouTube connection failed'];
    }

    private function testTelegramConnection(SocialAccount $account): array
    {
        $response = Http::get("https://api.telegram.org/bot{$account->access_token}/getMe");

        if ($response->successful() && $response->json()['ok']) {
            return ['success' => true, 'message' => 'Telegram bot connection is working'];
        }

        return ['success' => false, 'message' => 'Telegram bot connection failed'];
    }
}
