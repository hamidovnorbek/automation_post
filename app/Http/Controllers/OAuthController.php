<?php

namespace App\Http\Controllers;

use App\Models\SocialAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redirect;
use Carbon\Carbon;

class OAuthController extends Controller
{
    /**
     * Redirect to Facebook OAuth
     */
    public function redirectToFacebook()
    {
        $clientId = config('services.facebook.client_id');
        $redirectUri = route('oauth.facebook.callback');
        $scope = 'pages_manage_posts,pages_read_engagement,instagram_basic';

        $url = "https://www.facebook.com/v18.0/dialog/oauth?" . http_build_query([
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'scope' => $scope,
            'response_type' => 'code',
            'state' => csrf_token(),
        ]);

        return redirect($url);
    }

    /**
     * Handle Facebook OAuth callback
     */
    public function handleFacebookCallback(Request $request)
    {
        if ($request->has('error')) {
            return redirect()->route('filament.admin.resources.social-accounts.index')
                ->with('error', 'Facebook authorization was denied.');
        }

        $code = $request->get('code');
        if (!$code) {
            return redirect()->route('filament.admin.resources.social-accounts.index')
                ->with('error', 'No authorization code received.');
        }

        try {
            // Exchange code for access token
            $response = Http::post('https://graph.facebook.com/v18.0/oauth/access_token', [
                'client_id' => config('services.facebook.client_id'),
                'client_secret' => config('services.facebook.client_secret'),
                'redirect_uri' => route('oauth.facebook.callback'),
                'code' => $code,
            ]);

            $data = $response->json();

            if (isset($data['error'])) {
                throw new \Exception($data['error']['message'] ?? 'Facebook API error');
            }

            // Get user info
            $userResponse = Http::get('https://graph.facebook.com/me', [
                'access_token' => $data['access_token'],
                'fields' => 'id,name,email',
            ]);

            $userData = $userResponse->json();

            // Save or update social account
            SocialAccount::updateOrCreate(
                [
                    'user_id' => auth()->id(),
                    'platform_name' => 'facebook',
                ],
                [
                    'access_token' => $data['access_token'],
                    'refresh_token' => $data['refresh_token'] ?? null,
                    'expires_at' => isset($data['expires_in'])
                        ? Carbon::now()->addSeconds($data['expires_in'])
                        : null,
                    'account_username' => $userData['name'] ?? null,
                    'meta' => [
                        'user_id' => $userData['id'] ?? null,
                        'email' => $userData['email'] ?? null,
                    ],
                    'is_active' => true,
                ]
            );

            return redirect()->route('filament.admin.resources.social-accounts.index')
                ->with('success', 'Facebook account connected successfully!');

        } catch (\Exception $e) {
            Log::error('Facebook OAuth error: ' . $e->getMessage());

            return redirect()->route('filament.admin.resources.social-accounts.index')
                ->with('error', 'Failed to connect Facebook account: ' . $e->getMessage());
        }
    }

    /**
     * Redirect to YouTube OAuth (via Google)
     */
    public function redirectToYoutube()
    {
        $clientId = config('services.google.client_id');
        $redirectUri = route('oauth.youtube.callback');
        $scope = 'https://www.googleapis.com/auth/youtube.upload https://www.googleapis.com/auth/youtube';

        $url = "https://accounts.google.com/o/oauth2/auth?" . http_build_query([
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'scope' => $scope,
            'response_type' => 'code',
            'access_type' => 'offline',
            'prompt' => 'consent',
            'state' => csrf_token(),
        ]);

        return redirect($url);
    }

    /**
     * Handle YouTube OAuth callback
     */
    public function handleYoutubeCallback(Request $request)
    {
        if ($request->has('error')) {
            return redirect()->route('filament.admin.resources.social-accounts.index')
                ->with('error', 'YouTube authorization was denied.');
        }

        $code = $request->get('code');
        if (!$code) {
            return redirect()->route('filament.admin.resources.social-accounts.index')
                ->with('error', 'No authorization code received.');
        }

        try {
            // Exchange code for access token
            $response = Http::post('https://oauth2.googleapis.com/token', [
                'client_id' => config('services.google.client_id'),
                'client_secret' => config('services.google.client_secret'),
                'redirect_uri' => route('oauth.youtube.callback'),
                'grant_type' => 'authorization_code',
                'code' => $code,
            ]);

            $data = $response->json();

            if (isset($data['error'])) {
                throw new \Exception($data['error_description'] ?? 'Google API error');
            }

            // Get channel info
            $channelResponse = Http::get('https://www.googleapis.com/youtube/v3/channels', [
                'access_token' => $data['access_token'],
                'part' => 'snippet',
                'mine' => 'true',
            ]);

            $channelData = $channelResponse->json();
            $channel = $channelData['items'][0] ?? null;

            // Save or update social account
            SocialAccount::updateOrCreate(
                [
                    'user_id' => auth()->id(),
                    'platform_name' => 'youtube',
                ],
                [
                    'access_token' => $data['access_token'],
                    'refresh_token' => $data['refresh_token'] ?? null,
                    'expires_at' => isset($data['expires_in'])
                        ? Carbon::now()->addSeconds($data['expires_in'])
                        : null,
                    'account_username' => $channel['snippet']['title'] ?? null,
                    'meta' => [
                        'channel_id' => $channel['id'] ?? null,
                        'description' => $channel['snippet']['description'] ?? null,
                    ],
                    'is_active' => true,
                ]
            );

            return redirect()->route('filament.admin.resources.social-accounts.index')
                ->with('success', 'YouTube channel connected successfully!');

        } catch (\Exception $e) {
            Log::error('YouTube OAuth error: ' . $e->getMessage());

            return redirect()->route('filament.admin.resources.social-accounts.index')
                ->with('error', 'Failed to connect YouTube channel: ' . $e->getMessage());
        }
    }

    /**
     * Handle Telegram bot connection (manual token input)
     */
    public function connectTelegram(Request $request)
    {
        $request->validate([
            'bot_token' => 'required|string',
            'chat_id' => 'required|string',
        ]);

        try {
            // Verify bot token by getting bot info
            $response = Http::get("https://api.telegram.org/bot{$request->bot_token}/getMe");
            $data = $response->json();

            if (!$data['ok']) {
                throw new \Exception('Invalid bot token');
            }

            $botInfo = $data['result'];

            // Save or update social account
            SocialAccount::updateOrCreate(
                [
                    'user_id' => auth()->id(),
                    'platform_name' => 'telegram',
                ],
                [
                    'access_token' => $request->bot_token,
                    'account_username' => '@' . $botInfo['username'],
                    'meta' => [
                        'bot_id' => $botInfo['id'],
                        'bot_name' => $botInfo['first_name'],
                        'chat_id' => $request->chat_id,
                    ],
                    'is_active' => true,
                ]
            );

            return response()->json([
                'success' => true,
                'message' => 'Telegram bot connected successfully!'
            ]);

        } catch (\Exception $e) {
            Log::error('Telegram connection error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to connect Telegram bot: ' . $e->getMessage()
            ], 400);
        }
    }

    /**
     * Disconnect a social account
     */
    public function disconnect(Request $request, string $platform)
    {
        $account = SocialAccount::where('user_id', auth()->id())
            ->where('platform_name', $platform)
            ->first();

        if ($account) {
            $account->delete();

            return redirect()->route('filament.admin.resources.social-accounts.index')
                ->with('success', ucfirst($platform) . ' account disconnected successfully!');
        }

        return redirect()->route('filament.admin.resources.social-accounts.index')
            ->with('error', 'Account not found.');
    }
}
