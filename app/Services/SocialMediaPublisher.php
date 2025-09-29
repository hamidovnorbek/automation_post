<?php

namespace App\Services;

use App\Models\Post;
use App\Models\PostSocial;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SocialMediaPublisher
{
    public function publish(Post $post): void
    {
        $platforms = $post->social_medias;
        if (!is_array($platforms) || empty($platforms)) {
            // Backwards compatibility: default to Facebook only
            $platforms = ['facebook'];
        }

        foreach ($platforms as $platform) {
            $platform = strtolower((string) $platform);
            $record = PostSocial::updateOrCreate(
                [
                    'post_id' => $post->id,
                    'platform' => $platform,
                ],
                [
                    'status' => 'pending',
                    'response' => null,
                ]
            );

            try {
                $response = $this->dispatchToPlatform($platform, $post);
                $record->update([
                    'status' => 'posted',
                    'response' => $response,
                ]);
                Log::info('Post published to platform', [
                    'post_id' => $post->id,
                    'platform' => $platform,
                    'response' => $response,
                ]);
            } catch (\Throwable $e) {
                $record->update([
                    'status' => 'failed',
'response' => [
                        'error' => $e->getMessage(),
                        'trace' => config('app.debug') ? $e->getTraceAsString() : null,
                    ],
                ]);
                Log::error('Failed to publish to platform', [
                    'post_id' => $post->id,
                    'platform' => $platform,
                    'error' => $e->getMessage(),
                ]);
                // Continue with next platforms
            }
        }
    }

    protected function dispatchToPlatform(string $platform, Post $post): array
    {
        switch ($platform) {
            case 'facebook':
                app(FacebookService::class)->publish($post);
                return ['ok' => true];
            case 'instagram':
                return app(InstagramService::class)->publish($post);
            case 'telegram':
                return app(TelegramService::class)->publish($post);
            default:
                throw new \InvalidArgumentException('Unsupported platform: ' . $platform);
        }
    }
}
