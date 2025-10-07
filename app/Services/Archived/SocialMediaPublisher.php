<?php

namespace App\Services;

use App\Models\Post;
use App\Models\PostPublication;
use App\Services\SocialMedia\FacebookService;
use App\Services\SocialMedia\InstagramService;
use App\Services\SocialMedia\TelegramService;
use Illuminate\Support\Facades\Log;

class SocialMediaPublisher
{
    protected array $services = [];

    public function __construct()
    {
        $this->services = [
            'facebook' => new FacebookService(),
            'instagram' => new InstagramService(),
            'telegram' => new TelegramService(),
        ];
    }

    public function publishPost(Post $post): array
    {
        $results = [];
        $publications = $post->publications()->where('status', 'pending')->get();

        foreach ($publications as $publication) {
            $service = $this->getService($publication->platform);
            if (!$service) {
                $publication->markAsFailed("Service not available for platform: {$publication->platform}");
                $results[$publication->platform] = false;
                continue;
            }

            try {
                $result = $service->publish($publication);
                $results[$publication->platform] = $result;
            } catch (\Exception $e) {
                Log::error("Failed to publish to {$publication->platform}", [
                    'post_id' => $post->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                $results[$publication->platform] = false;
            }
        }

        return $results;
    }

    public function publishScheduledPosts(): int
    {
        $scheduledPublications = PostPublication::where('status', 'scheduled')
            ->where('scheduled_for', '<=', now())
            ->with('post')
            ->get();

        $publishedCount = 0;

        foreach ($scheduledPublications as $publication) {
            $service = $this->getService($publication->platform);
            if (!$service) {
                $publication->markAsFailed("Service not available for platform: {$publication->platform}");
                continue;
            }

            try {
                if ($service->publish($publication)) {
                    $publishedCount++;
                }
            } catch (\Exception $e) {
                Log::error("Failed to publish scheduled post to {$publication->platform}", [
                    'publication_id' => $publication->id,
                    'post_id' => $publication->post_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $publishedCount;
    }

    public function retryFailedPublications(Post $post): array
    {
        $failedPublications = $post->publications()
            ->where('status', 'failed')
            ->where('retry_count', '<', 3)
            ->get();

        $results = [];

        foreach ($failedPublications as $publication) {
            $service = $this->getService($publication->platform);
            if (!$service) {
                continue;
            }

            try {
                $result = $service->publish($publication);
                $results[$publication->platform] = $result;
            } catch (\Exception $e) {
                Log::error("Retry failed for {$publication->platform}", [
                    'post_id' => $post->id,
                    'error' => $e->getMessage(),
                ]);
                $results[$publication->platform] = false;
            }
        }

        return $results;
    }

    protected function getService(string $platform)
    {
        return $this->services[$platform] ?? null;
    }

    public function testConnections(): array
    {
        $results = [];

        foreach ($this->services as $platform => $service) {
            try {
                $service->validateConfig();
                $results[$platform] = ['status' => 'connected', 'error' => null];
            } catch (\Exception $e) {
                $results[$platform] = ['status' => 'error', 'error' => $e->getMessage()];
            }
        }

        return $results;
    }
}
