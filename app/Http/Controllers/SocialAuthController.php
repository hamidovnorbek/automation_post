<?php

namespace App\Http\Controllers;

use App\Models\SocialAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Carbon\Carbon;

class SocialAuthController extends Controller
{
    /**
     * Show the social connections dashboard
     */
    public function index(): View
    {
        $user = Auth::user();
        $connections = $user->socialAccounts()->get();
        
        $platforms = [
            'facebook' => [
                'name' => 'Facebook',
                'icon' => 'fab fa-facebook-f',
                'color' => 'bg-blue-600 hover:bg-blue-700',
                'description' => 'Connect your Facebook page to post updates',
                'scopes' => ['pages_manage_posts', 'pages_read_engagement'],
            ],
            'instagram' => [
                'name' => 'Instagram',
                'icon' => 'fab fa-instagram',
                'color' => 'bg-gradient-to-r from-purple-500 to-pink-500 hover:from-purple-600 hover:to-pink-600',
                'description' => 'Connect your Instagram Business account',
                'scopes' => ['instagram_basic', 'instagram_content_publish'],
            ],
            'youtube' => [
                'name' => 'YouTube',
                'icon' => 'fab fa-youtube',
                'color' => 'bg-red-600 hover:bg-red-700',
                'description' => 'Connect your YouTube channel to upload videos',
                'scopes' => ['youtube.upload', 'youtube.readonly'],
            ],
            'telegram' => [
                'name' => 'Telegram',
                'icon' => 'fab fa-telegram-plane',
                'color' => 'bg-blue-500 hover:bg-blue-600',
                'description' => 'Connect your Telegram bot for channel posting',
                'scopes' => [],
            ],
        ];

        // Add connection status to each platform
        foreach ($platforms as $key => &$platform) {
            $connection = $connections->where('platform_name', $key)->first();
            $platform['connected'] = $connection !== null;
            $platform['connection'] = $connection;
            $platform['status'] = $connection ? $connection->getStatusLabel() : 'Not Connected';
            $platform['status_color'] = $connection ? $connection->getStatusColor() : 'gray';
        }

        return view('social-connections.index', compact('platforms', 'connections'));
    }

    /**
     * Redirect to OAuth provider
     */
    public function redirect(string $provider): RedirectResponse
    {
        if (!in_array($provider, ['facebook', 'google', 'instagram'])) {
            return redirect()->route('social.connections')
                ->with('error', 'Invalid social media provider.');
        }

        try {
            $scopes = $this->getProviderScopes($provider);
            
            // Store provider in session for callback
            session(['oauth_provider' => $provider]);

            return Socialite::driver($provider)->redirect();

        } catch (\Exception $e) {
            Log::error('OAuth redirect error', [
                'provider' => $provider,
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
            ]);

            return redirect()->route('social.connections')
                ->with('error', 'Failed to connect to ' . ucfirst($provider) . '. Please try again.');
        }
    }

    /**
     * Handle OAuth callback
     */
    public function callback(string $provider): RedirectResponse
    {
        $sessionProvider = session('oauth_provider');
        
        if ($provider !== $sessionProvider) {
            return redirect()->route('social.connections')
                ->with('error', 'Invalid OAuth session.');
        }

        try {
            $socialUser = Socialite::driver($provider)->user();
            
            // Handle different providers
            $accountData = match($provider) {
                'facebook' => $this->handleFacebookCallback($socialUser),
                'google' => $this->handleGoogleCallback($socialUser),
                'instagram' => $this->handleInstagramCallback($socialUser),
                default => throw new \Exception('Unsupported provider'),
            };

            // Store or update the social account
            $socialAccount = SocialAccount::updateOrCreate(
                [
                    'user_id' => Auth::id(),
                    'platform_name' => $accountData['platform'],
                ],
                [
                    'provider_id' => $accountData['provider_id'],
                    'provider_name' => $accountData['provider_name'],
                    'access_token' => $accountData['access_token'],
                    'refresh_token' => $accountData['refresh_token'] ?? null,
                    'expires_at' => $accountData['expires_at'] ?? null,
                    'account_username' => $accountData['username'],
                    'meta' => $accountData['meta'],
                    'is_active' => true,
                ]
            );

            Log::info('Social account connected successfully', [
                'provider' => $provider,
                'user_id' => Auth::id(),
                'account_id' => $socialAccount->id,
            ]);

            session()->forget('oauth_provider');

            return redirect()->route('social.connections')
                ->with('success', ucfirst($provider) . ' account connected successfully!');

        } catch (\Exception $e) {
            Log::error('OAuth callback error', [
                'provider' => $provider,
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
            ]);

            session()->forget('oauth_provider');

            return redirect()->route('social.connections')
                ->with('error', 'Failed to connect ' . ucfirst($provider) . ' account: ' . $e->getMessage());
        }
    }

    /**
     * Show Telegram bot connection form
     */
    public function telegramForm(): View
    {
        return view('social-connections.telegram-form');
    }

    /**
     * Connect Telegram bot
     */
    public function connectTelegram(Request $request): RedirectResponse
    {
        $request->validate([
            'bot_token' => 'required|string',
            'chat_id' => 'required|string',
        ]);

        try {
            // Test the bot token
            $response = \Illuminate\Support\Facades\Http::get("https://api.telegram.org/bot{$request->bot_token}/getMe");
            
            if (!$response->successful() || !$response->json()['ok']) {
                throw new \Exception('Invalid bot token');
            }

            $botInfo = $response->json()['result'];

            // Store the Telegram account
            SocialAccount::updateOrCreate(
                [
                    'user_id' => Auth::id(),
                    'platform_name' => 'telegram',
                ],
                [
                    'provider_id' => $botInfo['id'],
                    'provider_name' => $botInfo['first_name'],
                    'access_token' => $request->bot_token,
                    'account_username' => $botInfo['username'],
                    'meta' => [
                        'bot_id' => $botInfo['id'],
                        'bot_name' => $botInfo['first_name'],
                        'chat_id' => $request->chat_id,
                        'can_join_groups' => $botInfo['can_join_groups'] ?? false,
                        'can_read_all_group_messages' => $botInfo['can_read_all_group_messages'] ?? false,
                    ],
                    'is_active' => true,
                ]
            );

            return redirect()->route('social.connections')
                ->with('success', 'Telegram bot connected successfully!');

        } catch (\Exception $e) {
            return redirect()->back()
                ->withErrors(['error' => 'Failed to connect Telegram bot: ' . $e->getMessage()])
                ->withInput();
        }
    }

    /**
     * Disconnect a social account
     */
    public function disconnect(string $platform): RedirectResponse
    {
        $account = Auth::user()->socialAccounts()
            ->where('platform_name', $platform)
            ->first();

        if (!$account) {
            return redirect()->route('social.connections')
                ->with('error', 'Account not found.');
        }

        $account->delete();

        Log::info('Social account disconnected', [
            'provider' => $platform,
            'user_id' => Auth::id(),
        ]);

        return redirect()->route('social.connections')
            ->with('success', ucfirst($platform) . ' account disconnected successfully.');
    }

    /**
     * Test connection for a platform
     */
    public function testConnection(string $platform): RedirectResponse
    {
        $account = Auth::user()->socialAccounts()
            ->where('platform_name', $platform)
            ->first();

        if (!$account) {
            return redirect()->route('social.connections')
                ->with('error', 'Account not connected.');
        }

        $socialMediaService = app(\App\Services\SocialMediaService::class);
        $result = $socialMediaService->testConnection($account);

        if ($result['success']) {
            return redirect()->route('social.connections')
                ->with('success', $result['message']);
        } else {
            return redirect()->route('social.connections')
                ->with('error', $result['message']);
        }
    }

    /**
     * Get provider-specific scopes
     */
    private function getProviderScopes(string $provider): array
    {
        return match($provider) {
            'facebook' => [
                'pages_manage_posts',
                'pages_read_engagement', 
                'pages_show_list',
                'instagram_basic',
                'instagram_content_publish'
            ],
            'google' => [
                'https://www.googleapis.com/auth/youtube.upload',
                'https://www.googleapis.com/auth/youtube.readonly',
                'https://www.googleapis.com/auth/userinfo.profile',
                'https://www.googleapis.com/auth/userinfo.email'
            ],
            'instagram' => [
                'instagram_basic',
                'instagram_content_publish'
            ],
            default => [],
        };
    }

    /**
     * Handle Facebook OAuth callback
     */
    private function handleFacebookCallback($socialUser): array
    {
        $expiresAt = null;
        if (isset($socialUser->expiresIn)) {
            $expiresAt = Carbon::now()->addSeconds($socialUser->expiresIn);
        }

        return [
            'platform' => 'facebook',
            'provider_id' => $socialUser->getId(),
            'provider_name' => $socialUser->getName(),
            'access_token' => $socialUser->token,
            'refresh_token' => $socialUser->refreshToken,
            'expires_at' => $expiresAt,
            'username' => $socialUser->getNickname() ?? $socialUser->getName(),
            'meta' => [
                'user_id' => $socialUser->getId(),
                'name' => $socialUser->getName(),
                'email' => $socialUser->getEmail(),
                'avatar' => $socialUser->getAvatar(),
            ],
        ];
    }

    /**
     * Handle Google OAuth callback (for YouTube)
     */
    private function handleGoogleCallback($socialUser): array
    {
        $expiresAt = null;
        if (isset($socialUser->expiresIn)) {
            $expiresAt = Carbon::now()->addSeconds($socialUser->expiresIn);
        }

        return [
            'platform' => 'youtube',
            'provider_id' => $socialUser->getId(),
            'provider_name' => $socialUser->getName(),
            'access_token' => $socialUser->token,
            'refresh_token' => $socialUser->refreshToken,
            'expires_at' => $expiresAt,
            'username' => $socialUser->getNickname() ?? $socialUser->getName(),
            'meta' => [
                'user_id' => $socialUser->getId(),
                'name' => $socialUser->getName(),
                'email' => $socialUser->getEmail(),
                'avatar' => $socialUser->getAvatar(),
            ],
        ];
    }

    /**
     * Handle Instagram OAuth callback (uses Facebook OAuth)
     */
    private function handleInstagramCallback($socialUser): array
    {
        return [
            'platform' => 'instagram',
            'provider_id' => $socialUser->getId(),
            'provider_name' => $socialUser->getName(),
            'access_token' => $socialUser->token,
            'refresh_token' => $socialUser->refreshToken,
            'expires_at' => Carbon::now()->addSeconds($socialUser->expiresIn ?? 3600),
            'username' => $socialUser->getNickname() ?? $socialUser->getName(),
            'meta' => [
                'user_id' => $socialUser->getId(),
                'name' => $socialUser->getName(),
                'email' => $socialUser->getEmail(),
                'avatar' => $socialUser->getAvatar(),
            ],
        ];
    }
}