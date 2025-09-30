<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class VerifyWebhookSignature
{
    /**
     * Handle an incoming webhook request and verify signature.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string|null  $platform
     * @return mixed
     */
    public function handle(Request $request, Closure $next, ?string $platform = null)
    {
        // Skip verification in local environment for testing
        if (app()->environment('local') && !config('app.verify_webhooks_locally', false)) {
            Log::info('Webhook signature verification skipped in local environment');
            return $next($request);
        }

        switch ($platform) {
            case 'facebook':
                return $this->verifyFacebookSignature($request, $next);
            case 'instagram':
                return $this->verifyInstagramSignature($request, $next);
            case 'telegram':
                return $this->verifyTelegramSignature($request, $next);
            default:
                // Generic signature verification
                return $this->verifyGenericSignature($request, $next);
        }
    }

    /**
     * Verify Facebook webhook signature
     *
     * @param Request $request
     * @param Closure $next
     * @return mixed
     */
    private function verifyFacebookSignature(Request $request, Closure $next)
    {
        $signature = $request->header('X-Hub-Signature-256');
        $appSecret = config('services.facebook.app_secret');

        if (!$signature || !$appSecret) {
            Log::warning('Facebook webhook signature or app secret missing');
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $expectedSignature = 'sha256=' . hash_hmac('sha256', $request->getContent(), $appSecret);

        if (!hash_equals($expectedSignature, $signature)) {
            Log::warning('Facebook webhook signature verification failed', [
                'expected' => $expectedSignature,
                'received' => $signature,
            ]);
            return response()->json(['error' => 'Invalid signature'], 401);
        }

        Log::info('Facebook webhook signature verified successfully');
        return $next($request);
    }

    /**
     * Verify Instagram webhook signature (same as Facebook)
     *
     * @param Request $request
     * @param Closure $next
     * @return mixed
     */
    private function verifyInstagramSignature(Request $request, Closure $next)
    {
        return $this->verifyFacebookSignature($request, $next);
    }

    /**
     * Verify Telegram webhook signature
     *
     * @param Request $request
     * @param Closure $next
     * @return mixed
     */
    private function verifyTelegramSignature(Request $request, Closure $next)
    {
        $secretToken = config('services.telegram.webhook_secret');

        if (!$secretToken) {
            Log::info('Telegram webhook secret not configured, skipping verification');
            return $next($request);
        }

        $telegramToken = $request->header('X-Telegram-Bot-Api-Secret-Token');

        if ($telegramToken !== $secretToken) {
            Log::warning('Telegram webhook token verification failed');
            return response()->json(['error' => 'Invalid token'], 401);
        }

        Log::info('Telegram webhook signature verified successfully');
        return $next($request);
    }

    /**
     * Generic webhook signature verification
     *
     * @param Request $request
     * @param Closure $next
     * @return mixed
     */
    private function verifyGenericSignature(Request $request, Closure $next)
    {
        $signature = $request->header('X-Signature') ?? $request->header('X-Hub-Signature');
        $secret = config('app.webhook_secret');

        if (!$secret) {
            Log::info('No webhook secret configured, skipping verification');
            return $next($request);
        }

        if (!$signature) {
            Log::warning('Webhook signature header missing');
            return response()->json(['error' => 'Signature required'], 401);
        }

        // Support both sha256= and raw hash formats
        if (strpos($signature, 'sha256=') === 0) {
            $signature = substr($signature, 7);
        }

        $expectedSignature = hash_hmac('sha256', $request->getContent(), $secret);

        if (!hash_equals($expectedSignature, $signature)) {
            Log::warning('Generic webhook signature verification failed');
            return response()->json(['error' => 'Invalid signature'], 401);
        }

        Log::info('Generic webhook signature verified successfully');
        return $next($request);
    }
}